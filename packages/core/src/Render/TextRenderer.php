<?php

declare(strict_types=1);

namespace Preflight\Core\Render;

use Preflight\Core\Report\Report;
use Preflight\Core\Report\Severity;
use Preflight\Core\Report\StatementResult;

/**
 * Terminal output, optionally with ANSI colours.
 */
final class TextRenderer
{
    public function __construct(private readonly bool $ansi = false) {}

    public function render(Report $report, Sources $sources = new Sources): string
    {
        $out = 'Preflight · MySQL '.$report->serverVersion.' · '.($report->sizeAware ? 'size-aware' : 'static (no table sizes)')."\n";

        if ($report->statements === []) {
            return $out."\nNo statements to analyse.\n";
        }

        foreach ($sources->groups($report) as $group) {
            $out .= "\n";
            if ($group['label'] !== null) {
                $out .= $this->bold($group['label'])."\n";
            }
            foreach ($group['statements'] as $statement) {
                $out .= $this->statement($statement);
            }
        }

        if ($report->advisories !== []) {
            $out .= "\n";
            foreach ($report->advisories as $advisory) {
                $out .= $this->color($advisory->severity, '['.$advisory->id.']').' '.$advisory->message."\n";
            }
        }

        return $out."\nOverall: ".$this->color($report->severity(), strtoupper($report->severity()->value))."\n";
    }

    private function statement(StatementResult $statement): string
    {
        $severity = $statement->severity();
        $out = $this->color($severity, $this->tag($severity)).' '.Sources::oneLine($statement->sql)."\n";

        if ($statement->effective !== null) {
            $e = $statement->effective;
            $out .= '        → ALGORITHM='.$e->algorithm->value.' LOCK='.$e->lock->value
                .($e->rebuildsTable() ? ' · rebuilds table' : '')
                .($e->blocksWrites() ? ' · BLOCKS WRITES' : '')
                .($statement->estimate !== null ? ' · '.$statement->estimate->format() : '')
                ."\n";
        }
        foreach ($statement->findings as $finding) {
            $out .= '        '.$this->color($finding->severity, '['.$finding->ruleId.']').' '.$finding->operation.': '.$finding->message."\n";
            if ($finding->suggestion !== null && $finding->severity !== Severity::Info) {
                $out .= '          ↳ '.$finding->suggestion."\n";
            }
        }
        foreach ($statement->advisories as $advisory) {
            $out .= '        '.$this->color($advisory->severity, '['.$advisory->id.']').' '.$advisory->message."\n";
        }

        return $out;
    }

    private function tag(Severity $severity): string
    {
        return match ($severity) {
            Severity::Danger => 'DANGER',
            Severity::Warn => 'WARN  ',
            Severity::Info => 'OK    ',
        };
    }

    private function color(Severity $severity, string $text): string
    {
        if (! $this->ansi) {
            return $text;
        }

        return match ($severity) {
            Severity::Danger => "\033[31m{$text}\033[0m",
            Severity::Warn => "\033[33m{$text}\033[0m",
            Severity::Info => "\033[32m{$text}\033[0m",
        };
    }

    private function bold(string $text): string
    {
        return $this->ansi ? "\033[1m{$text}\033[0m" : $text;
    }
}
