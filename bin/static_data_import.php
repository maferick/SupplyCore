#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

function static_data_import_parse_options(array $argv): array
{
    $options = getopt('', ['mode::', 'force']);
    $mode = trim((string) ($options['mode'] ?? 'auto'));
    if (!in_array($mode, ['auto', 'full', 'incremental'], true)) {
        throw new InvalidArgumentException('Argument --mode must be one of auto|full|incremental.');
    }

    return [
        'mode' => $mode,
        'force' => array_key_exists('force', $options),
    ];
}

function static_data_import_log(string $event, array $payload = [], $stream = STDOUT): void
{
    fwrite($stream, json_encode(['event' => $event, 'ts' => gmdate(DATE_ATOM)] + $payload, JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function static_data_import_main(array $argv): int
{
    try {
        $options = static_data_import_parse_options($argv);
        $result = static_data_import_reference_data($options['mode'], $options['force']);

        static_data_import_log('static_data.import.success', [
            'mode' => $result['mode'] ?? 'auto',
            'build_id' => $result['build_id'] ?? null,
            'changed' => (bool) ($result['changed'] ?? false),
            'rows_written' => (int) ($result['rows_written'] ?? 0),
        ]);

        return 0;
    } catch (Throwable $exception) {
        static_data_import_log('static_data.import.failed', ['error' => $exception->getMessage()], STDERR);

        return 1;
    }
}

exit(static_data_import_main($argv));
