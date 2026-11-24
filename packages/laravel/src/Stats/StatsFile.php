<?php

declare(strict_types=1);

namespace Preflight\Laravel\Stats;

use Preflight\Core\Stats\StatsSet;
use Preflight\Laravel\PreflightException;

/**
 * Production table sizes, as written by `preflight:stats` and read by `preflight:check --stats`.
 *
 *   {"preflight_stats": 1, "server_version": "8.0.36", "captured_at": "...", "tables": {"orders": {"rows": 1, "data_bytes": 1, "index_bytes": 0}}}
 *
 * A bare {"orders": {...}} map (the core CLI format) is accepted too.
 */
final class StatsFile
{
    public const FORMAT_VERSION = 1;

    /**
     * @param  array<string, array{rows: int, data_bytes: int, index_bytes: int}>  $tables
     */
    public function __construct(
        public readonly array $tables,
        public readonly ?string $serverVersion = null,
        public readonly ?string $capturedAt = null,
    ) {}

    public static function read(string $path): self
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new PreflightException("Cannot read the stats file [{$path}].");
        }

        $data = json_decode($contents, true);
        if (! is_array($data)) {
            throw new PreflightException("The stats file [{$path}] is not valid JSON.");
        }

        $tables = isset($data['tables']) && is_array($data['tables']) ? $data['tables'] : $data;
        $parsed = [];
        foreach ($tables as $name => $row) {
            if (! is_array($row)) {
                throw new PreflightException("The stats file [{$path}] has an invalid entry for [{$name}].");
            }
            $parsed[(string) $name] = [
                'rows' => self::int($row['rows'] ?? 0),
                'data_bytes' => self::int($row['data_bytes'] ?? 0),
                'index_bytes' => self::int($row['index_bytes'] ?? 0),
            ];
        }

        return new self(
            $parsed,
            isset($data['tables'], $data['server_version']) && is_string($data['server_version']) ? $data['server_version'] : null,
            isset($data['tables'], $data['captured_at']) && is_string($data['captured_at']) ? $data['captured_at'] : null,
        );
    }

    public function toStatsSet(): StatsSet
    {
        return StatsSet::fromArray($this->tables);
    }

    /**
     * @return array{preflight_stats: int, server_version: ?string, captured_at: ?string, tables: array<string, array{rows: int, data_bytes: int, index_bytes: int}>}
     */
    public function toArray(): array
    {
        return [
            'preflight_stats' => self::FORMAT_VERSION,
            'server_version' => $this->serverVersion,
            'captured_at' => $this->capturedAt,
            'tables' => $this->tables,
        ];
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
