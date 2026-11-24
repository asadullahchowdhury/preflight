<?php

declare(strict_types=1);

namespace Preflight\Core\Estimate;

use Preflight\Core\Report\Algorithm;
use Preflight\Core\Report\Classification;
use Preflight\Core\Report\Cost;
use Preflight\Core\Report\Estimate;
use Preflight\Core\Stats\TableStats;

/**
 * Rough duration ranges from table size and a throughput band (bytes per second).
 *
 * The default bands are conservative guesses for commodity cloud disks; they get replaced by
 * per-customer calibration from observed migration durations.
 */
final class DurationEstimator
{
    private const MB = 1_048_576;

    /**
     * @param  array{0: float, 1: float}  $indexBuild  [slow, fast] bytes/s scanning data to build an index
     * @param  array{0: float, 1: float}  $inplaceRebuild  [slow, fast] bytes/s rebuilding data + indexes in place
     * @param  array{0: float, 1: float}  $copy  [slow, fast] bytes/s copying row by row (ALGORITHM=COPY)
     */
    public function __construct(
        private readonly array $indexBuild = [30 * self::MB, 120 * self::MB],
        private readonly array $inplaceRebuild = [20 * self::MB, 80 * self::MB],
        private readonly array $copy = [10 * self::MB, 50 * self::MB],
    ) {}

    public function estimate(Classification $classification, TableStats $stats): Estimate
    {
        if ($classification->cost === Cost::Metadata) {
            return new Estimate(0.0, 1.0, 'metadata change; duration is dominated by waiting for the metadata lock');
        }

        if ($classification->algorithm === Algorithm::Copy) {
            return $this->range($stats->totalBytes(), $this->copy, 'copied row by row');
        }

        if ($classification->cost === Cost::TableRebuild) {
            return $this->range($stats->totalBytes(), $this->inplaceRebuild, 'rebuilt in place');
        }

        return $this->range($stats->dataBytes, $this->indexBuild, 'scanned to build the index');
    }

    /**
     * @param  array{0: float, 1: float}  $band
     */
    private function range(int $bytes, array $band, string $verb): Estimate
    {
        [$slow, $fast] = $band;
        $min = $bytes / $fast;
        $max = $bytes / $slow;

        return new Estimate($min, $max, sprintf('%s %s at %d–%d MB/s', self::bytes($bytes), $verb, (int) round($slow / self::MB), (int) round($fast / self::MB)));
    }

    public static function bytes(int $bytes): string
    {
        foreach (['GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024] as $unit => $size) {
            if ($bytes >= $size) {
                return round($bytes / $size, 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
