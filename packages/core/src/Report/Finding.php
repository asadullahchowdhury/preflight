<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * The verdict for one DDL operation (one ALTER clause, or one non-ALTER statement).
 */
final class Finding
{
    public function __construct(
        public readonly string $ruleId,
        public readonly ?string $table,
        public readonly string $operation,
        public readonly ?Classification $classification,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly ?string $suggestion = null,
    ) {}

    public function algorithm(): ?Algorithm
    {
        return $this->classification?->algorithm;
    }

    public function lock(): ?LockLevel
    {
        return $this->classification?->lock;
    }

    public function rebuildsTable(): bool
    {
        return $this->classification?->rebuildsTable() ?? false;
    }

    public function blocksWrites(): bool
    {
        return $this->classification?->blocksWrites() ?? false;
    }

    public function withSeverity(Severity $severity, ?string $message = null): self
    {
        return new self($this->ruleId, $this->table, $this->operation, $this->classification, $severity, $message ?? $this->message, $this->suggestion);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->ruleId,
            'table' => $this->table,
            'operation' => $this->operation,
            'algorithm' => $this->algorithm()?->value,
            'lock' => $this->lock()?->value,
            'cost' => $this->classification?->cost->value,
            'rebuilds_table' => $this->rebuildsTable(),
            'blocks_writes' => $this->blocksWrites(),
            'severity' => $this->severity->value,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
        ];
    }
}
