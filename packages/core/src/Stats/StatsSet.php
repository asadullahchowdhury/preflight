<?php

declare(strict_types=1);

namespace Preflight\Core\Stats;

final class StatsSet
{
    /** @var array<string, TableStats> */
    private array $tables = [];

    /**
     * @param  list<TableStats>  $tables
     */
    public function __construct(array $tables = [])
    {
        foreach ($tables as $stats) {
            $this->tables[strtolower($stats->table)] = $stats;
        }
    }

    /**
     * @param  array<string, array{rows?: int, data_bytes?: int, index_bytes?: int}>  $data
     */
    public static function fromArray(array $data): self
    {
        $tables = [];
        foreach ($data as $table => $row) {
            $tables[] = new TableStats((string) $table, (int) ($row['rows'] ?? 0), (int) ($row['data_bytes'] ?? 0), (int) ($row['index_bytes'] ?? 0));
        }

        return new self($tables);
    }

    public function for(?string $table): ?TableStats
    {
        return $table === null ? null : ($this->tables[strtolower($table)] ?? null);
    }
}
