<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * A cross-cutting note that is not tied to one operation's algorithm (metadata locks, repeated rebuilds…).
 */
final class Advisory
{
    public function __construct(
        public readonly string $id,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly ?string $table = null,
    ) {}

    /**
     * @return array{id: string, severity: string, table: ?string, message: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity->value,
            'table' => $this->table,
            'message' => $this->message,
        ];
    }
}
