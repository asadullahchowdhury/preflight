<?php

declare(strict_types=1);

use Preflight\Core\Report\Advisory;
use Preflight\Core\Report\Finding;
use Preflight\Core\Tests\Support\Fixture;

/*
 * Every case in tests/Fixtures/mysql/*.yaml: the engine's verdict must match the expectation exactly.
 */
dataset('fixtures', Fixture::all());

it('matches the expected verdict', function (Fixture $fixture) {
    $report = $fixture->analyze();

    $actual = array_map(static fn (Finding $f) => findingShape($f), $report->findings());
    $expected = $fixture->expectedFindings();

    expect(count($actual))->toBe(count($expected), 'finding count: '.json_encode($actual));

    foreach ($expected as $i => $want) {
        $got = $actual[$i];
        foreach ($want as $key => $value) {
            expect($got[$key] ?? null)->toBe($value, "finding #{$i} [{$key}] in ".json_encode($got));
        }
    }

    if (($statement = $fixture->expectedStatement()) !== null) {
        $effective = $report->statements[0]->effective;
        expect($effective)->not->toBeNull();
        $got = [
            'algorithm' => $effective?->algorithm->value,
            'lock' => $effective?->lock->value,
            'rebuild' => $effective?->rebuildsTable(),
        ];
        foreach ($statement as $key => $value) {
            expect($got[$key])->toBe($value, "statement [{$key}]");
        }
    }

    if (($advisories = $fixture->expectedAdvisories()) !== null) {
        $ids = [];
        foreach ($report->statements as $s) {
            array_push($ids, ...array_map(static fn (Advisory $a) => $a->id, $s->advisories));
        }
        array_push($ids, ...array_map(static fn (Advisory $a) => $a->id, $report->advisories));
        sort($ids);
        $want = $advisories;
        sort($want);
        expect($ids)->toBe($want);
    }

    if (($estimate = $fixture->expectedEstimate()) !== null) {
        $actualEstimate = $report->statements[0]->estimate;
        expect($actualEstimate)->not->toBeNull();
        if (isset($estimate['max_below'])) {
            expect($actualEstimate?->maxSeconds)->toBeLessThan((float) $estimate['max_below']);
        }
        if (isset($estimate['min_above'])) {
            expect($actualEstimate?->minSeconds)->toBeGreaterThan((float) $estimate['min_above']);
        }
    }
})->with('fixtures');

/**
 * @return array<string, mixed>
 */
function findingShape(Finding $finding): array
{
    return [
        'rule' => $finding->ruleId,
        'algorithm' => $finding->algorithm()?->value,
        'lock' => $finding->lock()?->value,
        'rebuild' => $finding->classification !== null ? $finding->rebuildsTable() : null,
        'severity' => $finding->severity->value,
    ];
}
