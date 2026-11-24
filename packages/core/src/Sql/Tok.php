<?php

declare(strict_types=1);

namespace Preflight\Core\Sql;

/**
 * A significant (non-whitespace, non-comment) token with a normalised shape.
 */
final class Tok
{
    public const WORD = 'word';      // keyword or bare identifier

    public const IDENT = 'ident';    // `quoted` identifier

    public const STRING = 'string';

    public const NUMBER = 'number';

    public const PUNCT = 'punct';    // ( ) , . = and other operators

    public function __construct(
        public readonly string $kind,
        public readonly string $value,
        public readonly int $raw,
    ) {}

    /** Upper-cased value for keyword matching. */
    public function upper(): string
    {
        return strtoupper($this->value);
    }

    public function isWord(string ...$words): bool
    {
        if ($this->kind !== self::WORD) {
            return false;
        }

        return $words === [] || in_array($this->upper(), $words, true);
    }

    public function isPunct(string $punct): bool
    {
        return $this->kind === self::PUNCT && $this->value === $punct;
    }

    /** Can this token name a table/column/index? */
    public function isName(): bool
    {
        return $this->kind === self::IDENT || $this->kind === self::WORD || $this->kind === self::STRING;
    }
}
