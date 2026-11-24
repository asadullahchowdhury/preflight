<?php

declare(strict_types=1);

use Preflight\Core\Estimate\DurationEstimator;
use Preflight\Core\Report\Classification;
use Preflight\Core\Report\Estimate;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Stats\TableStats;

$gb = 1024 ** 3;

it('estimates copy, rebuild and index build from table size', function () use ($gb) {
    $estimator = new DurationEstimator;
    $stats = new TableStats('orders', 41_000_000, 18 * $gb, 6 * $gb);

    $copy = $estimator->estimate(Classification::copy(), $stats);
    $rebuild = $estimator->estimate(Classification::inplaceRebuild(), $stats);
    $index = $estimator->estimate(Classification::inplaceIndexBuild(), $stats);

    // 24 GB at 10–50 MB/s
    expect($copy->minSeconds)->toEqualWithDelta(24 * 1024 / 50, 1)
        ->and($copy->maxSeconds)->toEqualWithDelta(24 * 1024 / 10, 1)
        // rebuild is faster than copy, index build only scans data
        ->and($rebuild->maxSeconds)->toBeLessThan($copy->maxSeconds)
        ->and($index->maxSeconds)->toEqualWithDelta(18 * 1024 / 30, 1);
});

it('treats metadata changes as sub-second', function () {
    $estimate = (new DurationEstimator)->estimate(Classification::instant(), new TableStats('t', 1, 1 << 40, 0));

    expect($estimate->maxSeconds)->toBeLessThanOrEqual(1.0)->and($estimate->format())->toBe('<1s');
});

it('formats ranges for humans', function (float $min, float $max, string $expected) {
    expect((new Estimate($min, $max, ''))->format())->toBe($expected);
})->with([
    [0.1, 0.5, '<1s'],
    [2, 9, '2s – 9s'],
    [100, 2400, '2 min – 40 min'],
    [3600, 18000, '60 min – 5 h'],
    [120, 130, '~2 min'],
]);

it('parses MySQL versions including vendor suffixes', function () {
    $server = ServerProfile::mysql('8.0.36-0ubuntu0.22.04.1');

    expect($server->version)->toBe('8.0.36')
        ->and($server->supportsInstantAnyPosition())->toBeTrue()
        ->and(ServerProfile::mysql('8.0.28')->supportsInstantRenameColumn())->toBeTrue()
        ->and(ServerProfile::mysql('8.0.27')->supportsInstantRenameColumn())->toBeFalse();
});

it('rejects MySQL 5.7', function () {
    ServerProfile::mysql('5.7.44');
})->throws(InvalidArgumentException::class);
