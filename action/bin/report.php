<?php

declare(strict_types=1);

/*
 * Turns preflight:check's JSON report into GitHub annotations (on the migration files) and step outputs.
 *
 *   php report.php preflight.json /path/to/workspace [annotations=true|false]
 *
 * Annotations go to stdout as workflow commands; outputs are appended to $GITHUB_OUTPUT.
 * Plain PHP with no dependencies, so it runs before or without the app's autoloader.
 */

[$script, $jsonPath, $workspace] = $argv + [null, null, null];
$annotate = ($argv[3] ?? 'true') !== 'false';

if (! is_string($jsonPath) || ! is_file($jsonPath)) {
    fwrite(STDERR, "Usage: php report.php preflight.json workspace [annotations]\n");
    exit(2);
}

$report = json_decode((string) file_get_contents($jsonPath), true);
if (! is_array($report)) {
    fwrite(STDERR, "Invalid report JSON: {$jsonPath}\n");
    exit(2);
}

$workspace = rtrim(str_replace('\\', '/', (string) (realpath((string) $workspace) ?: $workspace)), '/');
$files = is_array($report['migration_files'] ?? null) ? $report['migration_files'] : [];
$statements = is_array($report['statements'] ?? null) ? $report['statements'] : [];

$counts = ['danger' => 0, 'warn' => 0];
foreach ($statements as $statement) {
    $severity = $statement['severity'] ?? 'info';
    if (! isset($counts[$severity])) {
        continue;
    }
    $counts[$severity]++;

    if ($annotate) {
        echo annotation($statement, $files, $workspace), "\n";
    }
}

$outputs = [
    'severity' => (string) ($report['severity'] ?? 'info'),
    'danger-count' => (string) $counts['danger'],
    'warn-count' => (string) $counts['warn'],
    'migrations' => (string) count(is_array($report['migrations'] ?? null) ? $report['migrations'] : []),
];

$outputFile = getenv('GITHUB_OUTPUT');
$lines = '';
foreach ($outputs as $key => $value) {
    $lines .= "{$key}={$value}\n";
}
if (is_string($outputFile) && $outputFile !== '') {
    file_put_contents($outputFile, $lines, FILE_APPEND);
} else {
    echo $lines;
}

echo "Preflight: {$outputs['severity']} ({$counts['danger']} danger, {$counts['warn']} warn, {$outputs['migrations']} migrations checked)\n";

/**
 * @param  array<string, mixed>  $statement
 * @param  array<string, string>  $files
 */
function annotation(array $statement, array $files, string $workspace): string
{
    $level = ($statement['severity'] ?? '') === 'danger' ? 'error' : 'warning';
    $findings = is_array($statement['findings'] ?? null) ? $statement['findings'] : [];
    $advisories = is_array($statement['advisories'] ?? null) ? $statement['advisories'] : [];

    // The finding that set the statement's severity.
    usort($findings, static fn (array $a, array $b) => rank($b['severity'] ?? 'info') <=> rank($a['severity'] ?? 'info'));
    $main = $findings[0] ?? null;

    $how = [];
    if (is_string($statement['algorithm'] ?? null)) {
        $how[] = $statement['algorithm'];
    }
    if (($statement['blocks_writes'] ?? false) === true) {
        $how[] = 'blocks writes';
    }
    if (($statement['rebuilds_table'] ?? false) === true) {
        $how[] = 'rebuilds table';
    }
    if (is_array($statement['estimate'] ?? null) && is_string($statement['estimate']['label'] ?? null)) {
        $how[] = $statement['estimate']['label'];
    }
    $table = is_string($statement['table'] ?? null) ? $statement['table'] : null;
    $title = 'Preflight: '.($table !== null ? "`{$table}` " : '').($how !== [] ? implode(' · ', $how) : ($main['rule'] ?? 'check'));

    $message = trim(preg_replace('/\s+/', ' ', (string) ($statement['sql'] ?? '')) ?? '');
    if (is_array($main)) {
        $message .= "\n\n".($main['message'] ?? '');
        if (is_string($main['suggestion'] ?? null) && $main['suggestion'] !== '') {
            $message .= "\n\nSafer: ".$main['suggestion'];
        }
    }
    foreach ($advisories as $advisory) {
        if (($advisory['severity'] ?? 'info') !== 'info') {
            $message .= "\n\n".($advisory['message'] ?? '');
        }
    }

    $properties = ['title' => $title];
    $migration = is_string($statement['migration'] ?? null) ? $statement['migration'] : null;
    $path = $migration !== null ? ($files[$migration] ?? null) : null;
    if (is_string($path)) {
        $relative = str_starts_with(strtolower($path), strtolower($workspace.'/')) ? substr($path, strlen($workspace) + 1) : $path;
        $properties = ['file' => $relative, 'line' => (string) lineFor($path, $table, is_array($main) ? (string) ($main['operation'] ?? '') : '')] + $properties;
    }

    $props = [];
    foreach ($properties as $key => $value) {
        $props[] = $key.'='.escapeProperty($value);
    }

    return '::'.$level.' '.implode(',', $props).'::'.escapeData($message);
}

/**
 * Best line for the annotation: where the column (or table) the finding is about is named in the migration.
 */
function lineFor(string $path, ?string $table, string $operation): int
{
    $lines = @file($path) ?: [];
    // Identifiers in the operation, e.g. "ADD FOREIGN KEY `users_team_id_foreign` (`team_id`)": index, then columns.
    preg_match_all('/`([^`]+)`/', $operation, $m);
    $needles = $m[1];
    if ($table !== null) {
        $needles[] = $table;
    }

    foreach ($needles as $needle) {
        foreach ($lines as $number => $line) {
            if (str_contains($line, "'{$needle}'") || str_contains($line, "\"{$needle}\"")) {
                return $number + 1;
            }
        }
    }

    return 1;
}

function rank(string $severity): int
{
    return ['info' => 0, 'warn' => 1, 'danger' => 2][$severity] ?? 0;
}

function escapeData(string $value): string
{
    return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
}

function escapeProperty(string $value): string
{
    return str_replace(['%', "\r", "\n", ':', ','], ['%25', '%0D', '%0A', '%3A', '%2C'], $value);
}
