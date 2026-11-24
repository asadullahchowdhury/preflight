<?php

declare(strict_types=1);

use Preflight\Core\Report\Algorithm;
use Preflight\Core\Report\Cost;
use Preflight\Core\Schema\DefinitionReader;
use Preflight\Core\Schema\SchemaSnapshot;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Sql\ColumnDiff;

function diffColumn(string $old, string $new, string $table = 'utf8mb4', string $version = '8.0.30'): ColumnDiff
{
    $schema = SchemaSnapshot::fromSql("CREATE TABLE `t` ({$old}, KEY `t_indexed` (`indexed`)) DEFAULT CHARSET={$table}");
    $tableDef = $schema->table('t');
    $oldColumn = DefinitionReader::column($old);
    $newColumn = DefinitionReader::column($new);
    assert($tableDef !== null && $oldColumn !== null && $newColumn !== null);

    return new ColumnDiff($oldColumn, $newColumn, $tableDef, ServerProfile::mysql($version));
}

it('treats int display width as cosmetic', function () {
    $diff = diffColumn('`c` int(11) NOT NULL', '`c` int not null');

    expect($diff->changes)->toBe([])->and($diff->classification->algorithm)->toBe(Algorithm::Instant);
});

it('treats equivalent default spellings as equal', function (string $old, string $new) {
    expect(diffColumn($old, $new)->changes)->toBe([]);
})->with([
    'quoted number' => ["`c` int NOT NULL DEFAULT '0'", '`c` int not null default 0'],
    'now vs current_timestamp' => ['`c` timestamp NULL DEFAULT CURRENT_TIMESTAMP', '`c` timestamp null default now()'],
    'null default' => ['`c` varchar(10) DEFAULT NULL', '`c` varchar(10) null'],
]);

it('uses the charset to find the VARCHAR length-byte boundary', function (string $charset, int $from, int $to, Algorithm $expected) {
    $diff = diffColumn("`c` varchar({$from}) DEFAULT NULL", "`c` varchar({$to}) null", $charset);

    expect($diff->classification->algorithm)->toBe($expected);
})->with([
    'utf8mb4 63 → 64 crosses 255 bytes' => ['utf8mb4', 63, 64, Algorithm::Copy],
    'utf8mb4 20 → 63 stays under' => ['utf8mb4', 20, 63, Algorithm::Inplace],
    'latin1 100 → 255 stays under' => ['latin1', 100, 255, Algorithm::Inplace],
    'latin1 255 → 256 crosses' => ['latin1', 255, 256, Algorithm::Copy],
    'utf8mb3 85 → 86 crosses' => ['utf8mb3', 85, 86, Algorithm::Copy],
]);

it('lists every change and picks the most expensive', function () {
    $diff = diffColumn("`c` varchar(20) NOT NULL DEFAULT 'a'", "`c` varchar(20) null default 'b' comment 'x'");

    expect($diff->changes)->toBe(['NOT NULL → NULL', "default 'a' → 'b'", 'comment changed'])
        ->and($diff->classification->cost)->toBe(Cost::TableRebuild)
        ->and($diff->reason)->toBe('nullability change');
});

it('copies when a collation changes on an indexed column', function () {
    $diff = diffColumn('`indexed` varchar(20) DEFAULT NULL', '`indexed` varchar(20) collate utf8mb4_bin null');

    expect($diff->classification->algorithm)->toBe(Algorithm::Copy);
});

it('uses in-place metadata instead of instant before 8.0.12', function () {
    $diff = diffColumn("`c` varchar(20) DEFAULT 'a'", "`c` varchar(20) null default 'b'", version: '8.0.11');

    expect($diff->classification->algorithm)->toBe(Algorithm::Inplace)
        ->and($diff->classification->cost)->toBe(Cost::Metadata);
});
