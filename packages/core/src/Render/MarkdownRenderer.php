<?php

declare(strict_types=1);

namespace Preflight\Core\Render;

use Preflight\Core\Report\Advisory;
use Preflight\Core\Report\Report;
use Preflight\Core\Report\Severity;
use Preflight\Core\Report\StatementResult;

/**
 * GitHub-flavoured markdown for PR comments and job summaries.
 */
final class MarkdownRenderer
{
    /** Hidden marker so a bot can find and update its own comment. */
    public const MARKER = '<!-- preflight-report -->';

    public function render(Report $report, Sources $sources = new Sources, ?string $title = null): string
    {
        $risky = array_values(array_filter($report->statements, static fn (StatementResult $s) => $s->severity() !== Severity::Info));
        $safe = array_values(array_filter($report->statements, static fn (StatementResult $s) => $s->severity() === Severity::Info));

        $out = self::MARKER."\n";
        $out .= '### '.$this->icon($report->severity()).' '.($title ?? 'Preflight').': '.$this->headline($report)."\n\n";
        $out .= 'MySQL '.$report->serverVersion.' · '.($report->sizeAware
            ? 'size-aware (production table sizes)'
            : 'static analysis: no production table sizes, so no duration estimates')."\n";

        if ($risky !== []) {
            $out .= "\n".$this->table($risky, $sources, $report->sizeAware)."\n";
            foreach ($risky as $statement) {
                $out .= $this->details($statement, $sources);
            }
        }

        if ($safe !== []) {
            $out .= "\n<details><summary>".count($safe).' safe '.(count($safe) === 1 ? 'statement' : 'statements')."</summary>\n\n"
                .$this->table($safe, $sources, $report->sizeAware)."\n</details>\n";
        }

        $notes = $report->advisories;
        if ($notes !== []) {
            $out .= "\n<details><summary>Notes (".count($notes).")</summary>\n\n";
            foreach ($notes as $advisory) {
                $out .= $this->advisory($advisory);
            }
            $out .= "\n</details>\n";
        }

        return $out;
    }

    private function headline(Report $report): string
    {
        if ($report->statements === []) {
            return 'no migration statements to check';
        }

        $counts = ['danger' => 0, 'warn' => 0];
        foreach ($report->statements as $statement) {
            $severity = $statement->severity();
            if ($severity !== Severity::Info) {
                $counts[$severity->value]++;
            }
        }

        $parts = [];
        if ($counts['danger'] > 0) {
            $parts[] = $counts['danger'].' risky '.($counts['danger'] === 1 ? 'statement' : 'statements');
        }
        if ($counts['warn'] > 0) {
            $parts[] = $counts['warn'].' '.($counts['warn'] === 1 ? 'warning' : 'warnings');
        }
        if ($parts === []) {
            $total = count($report->statements);

            return 'all '.$total.' '.($total === 1 ? 'statement looks' : 'statements look').' safe';
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<StatementResult>  $statements
     */
    private function table(array $statements, Sources $sources, bool $sizeAware): string
    {
        $out = '| | Statement | How MySQL runs it |'.($sizeAware ? ' Estimate |' : '')."\n";
        $out .= '|:-:|---|---|'.($sizeAware ? '---|' : '')."\n";

        foreach ($statements as $statement) {
            $label = $sources->label($statement);
            $cell = '<code>'.$this->cell(Sources::oneLine($statement->sql, 90)).'</code>'
                .($label !== null ? '<br><sub>'.$this->cell($label).'</sub>' : '');
            $out .= '| '.$this->icon($statement->severity()).' | '.$cell.' | '.$this->cell($this->how($statement)).' |'
                .($sizeAware ? ' '.($statement->estimate?->format() ?? '–').' |' : '')."\n";
        }

        return $out;
    }

    private function how(StatementResult $statement): string
    {
        $e = $statement->effective;
        if ($e === null) {
            return $statement->findings[0]->ruleId ?? '–';
        }

        $parts = [$e->algorithm->value];
        if ($e->blocksWrites()) {
            $parts[] = '**blocks writes**';
        }
        if ($e->rebuildsTable()) {
            $parts[] = 'rebuilds table';
        }
        if ($statement->onNewTable) {
            $parts[] = 'new table';
        }

        return implode(' · ', $parts);
    }

    private function details(StatementResult $statement, Sources $sources): string
    {
        $label = $sources->label($statement);
        $fence = str_contains($statement->sql, '```') ? '~~~' : '```';

        $out = "\n#### ".$this->icon($statement->severity()).' '.($statement->table !== null ? '`'.$statement->table.'`' : 'Statement')
            .($label !== null ? ' · '.$label : '')."\n\n";
        $out .= $fence."sql\n".trim($statement->sql)."\n".$fence."\n\n";

        foreach ($statement->findings as $finding) {
            $out .= '- '.$this->icon($finding->severity).' **'.$finding->ruleId.'**: '.$finding->message."\n";
            if ($finding->suggestion !== null && $finding->severity !== Severity::Info) {
                $out .= '  - 💡 '.$finding->suggestion."\n";
            }
        }
        foreach ($statement->advisories as $advisory) {
            $out .= $this->advisory($advisory);
        }

        return $out;
    }

    private function advisory(Advisory $advisory): string
    {
        return '- '.$this->icon($advisory->severity).' **'.$advisory->id.'**: '.$advisory->message."\n";
    }

    private function icon(Severity $severity): string
    {
        return match ($severity) {
            Severity::Danger => '🔴',
            Severity::Warn => '🟡',
            Severity::Info => '🟢',
        };
    }

    private function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['&#124;', ' '], htmlspecialchars($text, ENT_NOQUOTES));
    }
}
