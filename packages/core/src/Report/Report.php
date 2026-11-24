<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

final class Report
{
    /**
     * @param  list<StatementResult>  $statements
     * @param  list<Advisory>  $advisories  Advisories spanning several statements.
     */
    public function __construct(
        public readonly string $serverVersion,
        public readonly bool $sizeAware,
        public readonly array $statements,
        public readonly array $advisories = [],
    ) {}

    public function severity(): Severity
    {
        $severity = Severity::Info;
        foreach ($this->statements as $statement) {
            $severity = $severity->max($statement->severity());
        }
        foreach ($this->advisories as $advisory) {
            $severity = $severity->max($advisory->severity);
        }

        return $severity;
    }

    /**
     * @return list<Finding>
     */
    public function findings(): array
    {
        $findings = [];
        foreach ($this->statements as $statement) {
            array_push($findings, ...$statement->findings);
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'server_version' => $this->serverVersion,
            'size_aware' => $this->sizeAware,
            'severity' => $this->severity()->value,
            'statements' => array_map(static fn (StatementResult $s) => $s->toArray(), $this->statements),
            'advisories' => array_map(static fn (Advisory $a) => $a->toArray(), $this->advisories),
        ];
    }
}
