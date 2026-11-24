<?php

declare(strict_types=1);

namespace Preflight\Core\Estimate;

/**
 * When a size-aware estimate becomes a warning or a danger. Defaults are a starting point, tuned in beta.
 */
final class Thresholds
{
    public function __construct(
        /** Writes blocked for at least this long → danger. */
        public readonly float $blockingDangerSeconds = 10.0,
        /** Writes blocked for at least this long → warn. */
        public readonly float $blockingWarnSeconds = 1.0,
        /** Non-blocking rebuild / index build running at least this long → warn (I/O load, replica lag). */
        public readonly float $longRunningWarnSeconds = 300.0,
    ) {}
}
