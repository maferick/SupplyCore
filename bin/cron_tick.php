#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

const CRON_TICK_RUNNER_LOCK = 'cron_tick_runner';

function cron_tick_output(string $event, array $payload = [], $stream = STDOUT): void
{
    $line = ['event' => $event, 'ts' => gmdate(DATE_ATOM)] + $payload;
    fwrite($stream, json_encode($line, JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function cron_tick_exit_code(array $summary): int
{
    return !empty($summary['scheduler_failed']) ? 1 : 0;
}

function cron_tick_main(): int
{
    $lockAcquired = false;

    try {
        $lockAcquired = runner_lock_acquire(CRON_TICK_RUNNER_LOCK);
        if (!$lockAcquired) {
            cron_tick_output('cron_tick.skipped_locked');

            return 0;
        }

        $summary = cron_tick_run();
        $summary['scheduler_failed'] = false;

        cron_tick_output('cron_tick.summary', [
            'jobs_due' => (int) ($summary['jobs_due'] ?? 0),
            'jobs_processed' => (int) ($summary['jobs_processed'] ?? 0),
            'jobs_succeeded' => (int) ($summary['jobs_succeeded'] ?? 0),
            'jobs_failed' => (int) ($summary['jobs_failed'] ?? 0),
            'scheduler_failed' => false,
        ]);

        return cron_tick_exit_code($summary);
    } catch (Throwable $exception) {
        cron_tick_output('cron_tick.scheduler_error', [
            'scheduler_failed' => true,
            'error' => $exception->getMessage(),
        ], STDERR);

        return 1;
    } finally {
        if ($lockAcquired) {
            runner_lock_release(CRON_TICK_RUNNER_LOCK);
        }
    }
}

exit(cron_tick_main());
