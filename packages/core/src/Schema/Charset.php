<?php

declare(strict_types=1);

namespace Preflight\Core\Schema;

final class Charset
{
    /** Maximum bytes per character, which decides VARCHAR length-byte size. Unknown charsets assume the worst. */
    public static function maxBytesPerChar(?string $charset): int
    {
        return match (strtolower((string) $charset)) {
            'latin1', 'latin2', 'latin5', 'latin7', 'ascii', 'binary', 'cp1250', 'cp1251', 'cp1256', 'cp1257',
            'cp850', 'cp852', 'cp866', 'dec8', 'greek', 'hebrew', 'hp8', 'keybcs2', 'koi8r', 'koi8u', 'macce',
            'macroman', 'swe7', 'tis620', 'armscii8', 'geostd8', 'armscii' => 1,
            'ucs2', 'gbk', 'big5', 'sjis', 'cp932', 'euckr', 'gb2312' => 2,
            'utf8', 'utf8mb3', 'ujis', 'eucjpms' => 3,
            default => 4,
        };
    }

    /** MySQL 8 default collation of common charsets; null when unknown. */
    public static function defaultCollation(string $charset): ?string
    {
        return match (strtolower($charset)) {
            'utf8mb4' => 'utf8mb4_0900_ai_ci',
            'utf8', 'utf8mb3' => 'utf8mb3_general_ci',
            'latin1' => 'latin1_swedish_ci',
            'ascii' => 'ascii_general_ci',
            'binary' => 'binary',
            'ucs2' => 'ucs2_general_ci',
            'utf16' => 'utf16_general_ci',
            'utf32' => 'utf32_general_ci',
            default => null,
        };
    }

    /** Charset part of a collation name, e.g. utf8mb4_unicode_ci → utf8mb4. */
    public static function fromCollation(string $collation): string
    {
        $collation = strtolower($collation);
        if ($collation === 'binary') {
            return 'binary';
        }

        return explode('_', $collation, 2)[0];
    }
}
