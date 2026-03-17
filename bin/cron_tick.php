#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

const CRON_TICK_JOB_DEFINITIONS = [
    [
        'job' => 'alliance-current',
        'enabled_setting' => 'alliance_current_pipeline_enabled',
        'interval_setting' => 'alliance_current_sync_interval_minutes',
        'interval_default' => 5,
        'source_env' => 'EVEMARKET_ALLIANCE_SOURCE_ID',
    ],
    [
        'job' => 'hub-history',
        'enabled_setting' => 'hub_history_pipeline_enabled',
        'interval_setting' => 'hub_history_sync_interval_minutes',
        'interval_default' => 15,
        'source_env' => 'EVEMARKET_HUB_SOURCE_ID',
    ],
    [
        'job' => 'alliance-history',
        'enabled_setting' => 'alliance_history_pipeline_enabled',
        'interval_setting' => 'alliance_history_sync_interval_minutes',
        'interval_default' => 60,
        'source_env' => 'EVEMARKET_ALLIANCE_SOURCE_ID',
    ],
    [
        'job' => 'maintenance-prune',
        'enabled_setting' => 'maintenance_prune_pipeline_enabled',
        'interval_setting' => 'maintenance_prune_sync_interval_minutes',
        'interval_default' => 1440,
        'source_env' => null,
    ],
];

function cron_tick_main(): int
{
    $lockHandle = cron_tick_acquire_lock();
    if ($lockHandle === null) {
        cron_tick_log(STDOUT, 'cron_tick.skipped_locked', []);

        return 0;
    }

    try {
        return cron_tick_run_due_jobs();
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function cron_tick_run_due_jobs(): int
{
    $hasFailures = false;
    $nowTimestamp = time();

    foreach (CRON_TICK_JOB_DEFINITIONS as $definition) {
        $job = (string) $definition['job'];
        $enabled = get_setting((string) $definition['enabled_setting'], '1') === '1';

        if (!$enabled) {
            cron_tick_log(STDOUT, 'cron_tick.job_skipped_disabled', ['job' => $job]);
            continue;
        }

        $intervalMinutes = max(1, (int) get_setting((string) $definition['interval_setting'], (string) $definition['interval_default']));
        $sourceId = cron_tick_source_id_from_env($definition['source_env']);
        if ($definition['source_env'] !== null && $sourceId === null) {
            $hasFailures = true;
            cron_tick_log(STDERR, 'cron_tick.job_skipped_invalid_source', [
                'job' => $job,
                'source_env' => (string) $definition['source_env'],
            ]);
            continue;
        }

        $datasetKey = cron_tick_dataset_key($job, $sourceId);

        try {
            $isDue = cron_tick_job_due($datasetKey, $intervalMinutes, $nowTimestamp);
        } catch (Throwable $exception) {
            $hasFailures = true;
            cron_tick_log(STDERR, 'cron_tick.job_due_check_failed', [
                'job' => $job,
                'dataset_key' => $datasetKey,
                'error' => $exception->getMessage(),
            ]);
            continue;
        }

        if (!$isDue) {
            continue;
        }

        $exitCode = cron_tick_run_sync_runner($job, $sourceId);
        if ($exitCode !== 0) {
            $hasFailures = true;
        }
    }

    return $hasFailures ? 1 : 0;
}

function cron_tick_acquire_lock()
{
    $lockPath = __DIR__ . '/../storage/cron_tick.lock';
    $lockDir = dirname($lockPath);

    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0775, true);
    }

    $handle = fopen($lockPath, 'c+');
    if ($handle === false) {
        cron_tick_log(STDERR, 'cron_tick.lock_open_failed', ['path' => $lockPath]);

        return null;
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);

        return null;
    }

    return $handle;
}

function cron_tick_source_id_from_env(?string $envKey): ?int
{
    if ($envKey === null || $envKey === '') {
        return null;
    }

    $raw = trim((string) getenv($envKey));
    if ($raw === '' || preg_match('/^[1-9][0-9]*$/', $raw) !== 1) {
        return null;
    }

    return (int) $raw;
}

function cron_tick_dataset_key(string $job, ?int $sourceId): string
{
    if ($job === 'alliance-current') {
        return sync_dataset_key_alliance_structure_orders_current((int) $sourceId);
    }

    if ($job === 'alliance-history') {
        return sync_dataset_key_alliance_structure_orders_history((int) $sourceId);
    }

    if ($job === 'hub-history') {
        return sync_dataset_key_market_hub_history_daily((string) $sourceId);
    }

    return sync_dataset_key_maintenance_history_prune();
}

function cron_tick_job_due(string $datasetKey, int $intervalMinutes, int $nowTimestamp): bool
{
    $state = db_sync_state_get($datasetKey);
    if ($state === null) {
        return true;
    }

    $lastSuccessRaw = trim((string) ($state['last_success_at'] ?? ''));
    if ($lastSuccessRaw === '') {
        return true;
    }

    $lastSuccessTimestamp = strtotime($lastSuccessRaw . ' UTC');
    if ($lastSuccessTimestamp === false) {
        return true;
    }

    return ($nowTimestamp - $lastSuccessTimestamp) >= ($intervalMinutes * 60);
}

function cron_tick_run_sync_runner(string $job, ?int $sourceId): int
{
    $parts = [
        PHP_BINARY,
        __DIR__ . '/sync_runner.php',
        '--job=' . $job,
        '--mode=incremental',
    ];

    if ($sourceId !== null) {
        $parts[] = '--source-id=' . $sourceId;
    }

    $command = implode(' ', array_map('escapeshellarg', $parts));

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        cron_tick_log(STDERR, 'cron_tick.job_spawn_failed', ['job' => $job]);

        return 1;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($stdout !== '') {
        fwrite(STDOUT, trim($stdout) . PHP_EOL);
    }

    if ($stderr !== '') {
        fwrite(STDERR, trim($stderr) . PHP_EOL);
    }

    cron_tick_log($exitCode === 0 ? STDOUT : STDERR, 'cron_tick.job_finished', [
        'job' => $job,
        'source_id' => $sourceId,
        'exit_code' => $exitCode,
    ]);

    return $exitCode;
}

function cron_tick_log($stream, string $event, array $payload): void
{
    $line = ['event' => $event, 'ts' => gmdate(DATE_ATOM)] + $payload;
    fwrite($stream, json_encode($line, JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

exit(cron_tick_main());
