<?php

declare(strict_types=1);

namespace Preflight\Core\Tests\Support;

use Preflight\Core\Analyzer;
use Preflight\Core\Report\Report;
use Preflight\Core\Schema\SchemaSnapshot;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Stats\StatsSet;
use Symfony\Component\Yaml\Yaml;

/**
 * One YAML test case: schema + SQL in, expected findings out.
 */
final class Fixture
{
    public const DEFAULT_SERVER = '8.0.30';

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly string $file,
        public readonly array $data,
    ) {}

    /**
     * @return array<string, array{0: self}>
     */
    public static function all(): array
    {
        $cases = [];
        foreach (glob(__DIR__.'/../Fixtures/mysql/*.yaml') ?: [] as $path) {
            $entries = Yaml::parseFile($path);
            if (! is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                $fixture = new self(basename($path, '.yaml'), $entry);
                $cases[$fixture->file.': '.$fixture->name()] = [$fixture];
            }
        }

        return $cases;
    }

    public function name(): string
    {
        return (string) $this->data['name'];
    }

    public function sql(): string
    {
        return (string) $this->data['sql'];
    }

    public function serverVersion(): string
    {
        return (string) ($this->data['server'] ?? self::DEFAULT_SERVER);
    }

    public function foreignKeyChecks(): bool
    {
        return (int) ($this->data['vars']['foreign_key_checks'] ?? 1) === 1;
    }

    public function schemaSql(): string
    {
        $schema = $this->data['schema'] ?? null;
        if ($schema === null) {
            return '';
        }
        $shared = Yaml::parseFile(__DIR__.'/../Fixtures/schemas.yaml');

        return is_array($shared) && isset($shared[$schema]) ? (string) $shared[$schema] : (string) $schema;
    }

    public function runsOnOracle(): bool
    {
        return ($this->data['oracle'] ?? true) !== false;
    }

    public function analyze(?string $serverVersion = null): Report
    {
        $stats = isset($this->data['stats']) ? StatsSet::fromArray($this->data['stats']) : null;

        return (new Analyzer)->analyze(
            $this->sql(),
            ServerProfile::mysql($serverVersion ?? $this->serverVersion(), $this->foreignKeyChecks()),
            SchemaSnapshot::fromSql($this->schemaSql()),
            $stats,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expectedFindings(): array
    {
        return array_values($this->data['expect'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function expectedStatement(): ?array
    {
        return $this->data['statement'] ?? null;
    }

    /**
     * @return list<string>|null
     */
    public function expectedAdvisories(): ?array
    {
        return array_key_exists('advisories', $this->data) ? array_values($this->data['advisories'] ?? []) : null;
    }

    /**
     * @return array{min_above?: float, max_below?: float}|null
     */
    public function expectedEstimate(): ?array
    {
        return $this->data['estimate'] ?? null;
    }
}
