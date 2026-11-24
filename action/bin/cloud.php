<?php

declare(strict_types=1);

/*
 * Small JSON helpers for scripts/cloud.sh (no jq needed).
 *
 *   php cloud.php get KEY < file.json           print a top-level string value
 *   php cloud.php urlencode VALUE               print VALUE url-encoded
 *   php cloud.php payload REPORT PR SHA BASE WS print the report with a "context" object added
 */

$command = $argv[1] ?? '';

switch ($command) {
    case 'get':
        $data = json_decode((string) stream_get_contents(STDIN), true);
        $value = is_array($data) ? ($data[$argv[2] ?? ''] ?? '') : '';
        echo is_scalar($value) ? (string) $value : '';
        break;

    case 'urlencode':
        echo rawurlencode($argv[2] ?? '');
        break;

    case 'payload':
        $report = json_decode((string) @file_get_contents($argv[2] ?? ''), true);
        if (! is_array($report)) {
            fwrite(STDERR, "Cannot read the report.\n");
            exit(1);
        }
        $report['context'] = [
            'pull_request' => ($argv[3] ?? '') === '' ? null : (int) $argv[3],
            'head_sha' => ($argv[4] ?? '') === '' ? null : $argv[4],
            'base_ref' => ($argv[5] ?? '') === '' ? null : $argv[5],
            'workspace' => $argv[6] ?? '',
        ];
        echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        break;

    default:
        fwrite(STDERR, "Usage: php cloud.php get|urlencode|payload …\n");
        exit(2);
}
