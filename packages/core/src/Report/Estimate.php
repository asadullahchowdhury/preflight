<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * A duration range. Always a range: table sizes from information_schema are estimates.
 */
final class Estimate
{
    public function __construct(
        public readonly float $minSeconds,
        public readonly float $maxSeconds,
        public readonly string $basis,
    ) {}

    public function format(): string
    {
        $min = self::human($this->minSeconds);
        $max = self::human($this->maxSeconds);

        if ($this->maxSeconds <= 1) {
            return '<1s';
        }

        return $min === $max ? '~'.$min : $min.' – '.$max;
    }

    public static function human(float $seconds): string
    {
        if ($seconds < 1) {
            return '<1s';
        }
        if ($seconds < 90) {
            return (int) round($seconds).'s';
        }
        if ($seconds < 5400) {
            return (int) round($seconds / 60).' min';
        }

        return round($seconds / 3600, 1).' h';
    }

    /**
     * @return array{min_seconds: float, max_seconds: float, label: string, basis: string}
     */
    public function toArray(): array
    {
        return [
            'min_seconds' => round($this->minSeconds, 2),
            'max_seconds' => round($this->maxSeconds, 2),
            'label' => $this->format(),
            'basis' => $this->basis,
        ];
    }
}
