<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

/**
 * Something Preflight cannot classify. Always reported, never silently passed.
 */
final class Unknown extends Operation
{
    public function __construct(?string $table, public readonly string $text, public readonly string $reason)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        $text = preg_replace('/\s+/', ' ', $this->text) ?? $this->text;

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77).'...' : $text;
    }
}
