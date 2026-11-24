<?php

declare(strict_types=1);

namespace Preflight\Core\Render;

use Preflight\Core\Report\Report;
use Preflight\Core\Report\StatementResult;

/**
 * Groups a report's statements by where they came from (a migration file, a script…).
 */
final class Sources
{
    /**
     * @param  array<int, string>  $labels  statement index => source label
     */
    public function __construct(private readonly array $labels = []) {}

    public function label(StatementResult $statement): ?string
    {
        return $this->labels[$statement->index] ?? null;
    }

    /**
     * @return list<array{label: ?string, statements: list<StatementResult>}>
     */
    public function groups(Report $report): array
    {
        $groups = [];
        $current = null;
        foreach ($report->statements as $statement) {
            $label = $this->label($statement);
            if ($current === null || $groups[$current]['label'] !== $label) {
                $groups[] = ['label' => $label, 'statements' => []];
                $current = count($groups) - 1;
            }
            $groups[$current]['statements'][] = $statement;
        }

        return $groups;
    }

    public static function oneLine(string $sql, int $max = 110): string
    {
        $sql = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);

        return mb_strlen($sql) > $max ? mb_substr($sql, 0, $max - 1).'…' : $sql;
    }
}
