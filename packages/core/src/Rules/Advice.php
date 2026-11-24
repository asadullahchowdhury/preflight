<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

/**
 * Reusable wording for safer alternatives.
 */
final class Advice
{
    public const ONLINE_SCHEMA_CHANGE = 'run it with gh-ost or pt-online-schema-change, which copy the table in the background without blocking writes';

    public const EXPAND_CONTRACT = 'Safer: add a new column, backfill it in batches (e.g. a queued job using chunkById), switch the code to it, then drop the old column in a later deploy. Or '.self::ONLINE_SCHEMA_CHANGE.'.';

    public const OFF_PEAK = 'Writes continue, but expect heavy I/O and replica lag on a large table; run it off-peak.';

    public static function blocking(string $what): string
    {
        return "{$what} Or ".self::ONLINE_SCHEMA_CHANGE.'.';
    }
}
