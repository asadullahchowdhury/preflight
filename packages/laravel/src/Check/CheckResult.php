<?php

declare(strict_types=1);

namespace Preflight\Laravel\Check;

use Preflight\Core\Render\Sources;
use Preflight\Core\Report\Report;

final class CheckResult
{
    /**
     * @param  list<string>  $migrations  names of the migrations analysed, in run order
     * @param  array<int, string>  $labels  statement index => migration name
     * @param  list<string>  $notes  things Preflight could not analyse, for the reader
     * @param  list<string>  $baseMigrations  migrations run first to build the "before" schema
     * @param  array<string, string>  $files  migration name => absolute file path
     */
    public function __construct(
        public readonly Report $report,
        public readonly array $migrations,
        public readonly array $labels,
        public readonly array $notes,
        public readonly array $baseMigrations,
        public readonly string $connectedVersion,
        public readonly array $files = [],
    ) {}

    public function sources(): Sources
    {
        return new Sources($this->labels);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $report = $this->report->toArray();
        $statements = is_array($report['statements'] ?? null) ? $report['statements'] : [];
        foreach ($statements as $i => $statement) {
            if (is_array($statement)) {
                $index = is_int($statement['index'] ?? null) ? $statement['index'] : -1;
                $statements[$i] = ['migration' => $this->labels[$index] ?? null] + $statement;
            }
        }
        $report['statements'] = $statements;

        return [
            'migrations' => $this->migrations,
            'migration_files' => array_map(static fn (string $path) => str_replace('\\', '/', (string) (realpath($path) ?: $path)), $this->files),
            'base_migrations' => count($this->baseMigrations),
            'connected_server_version' => $this->connectedVersion,
            'notes' => $this->notes,
        ] + $report;
    }
}
