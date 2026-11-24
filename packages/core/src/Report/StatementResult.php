<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * Everything Preflight knows about one SQL statement.
 */
final class StatementResult
{
    /**
     * @param  list<Finding>  $findings
     * @param  list<Advisory>  $advisories
     * @param  ?Classification  $effective  How MySQL runs the statement as a whole (ALTER/CREATE INDEX/DROP INDEX only).
     */
    public function __construct(
        public readonly int $index,
        public readonly string $sql,
        public readonly ?string $table,
        public readonly array $findings,
        public readonly ?Classification $effective,
        public readonly array $advisories = [],
        public readonly ?Estimate $estimate = null,
        public readonly bool $onNewTable = false,
    ) {}

    public function severity(): Severity
    {
        $severity = Severity::Info;
        foreach ($this->findings as $finding) {
            $severity = $severity->max($finding->severity);
        }
        foreach ($this->advisories as $advisory) {
            $severity = $severity->max($advisory->severity);
        }

        return $severity;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'sql' => $this->sql,
            'table' => $this->table,
            'severity' => $this->severity()->value,
            'algorithm' => $this->effective?->algorithm->value,
            'lock' => $this->effective?->lock->value,
            'cost' => $this->effective?->cost->value,
            'rebuilds_table' => $this->effective?->rebuildsTable() ?? false,
            'blocks_writes' => $this->effective?->blocksWrites() ?? false,
            'on_new_table' => $this->onNewTable,
            'estimate' => $this->estimate?->toArray(),
            'findings' => array_map(static fn (Finding $f) => $f->toArray(), $this->findings),
            'advisories' => array_map(static fn (Advisory $a) => $a->toArray(), $this->advisories),
        ];
    }
}
