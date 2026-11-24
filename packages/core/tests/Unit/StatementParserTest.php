<?php

declare(strict_types=1);

use Preflight\Core\Operations\AddColumn;
use Preflight\Core\Operations\AddForeignKey;
use Preflight\Core\Operations\AddIndex;
use Preflight\Core\Operations\DropTable;
use Preflight\Core\Operations\ModifyColumn;
use Preflight\Core\Operations\RenameTable;
use Preflight\Core\Operations\SetVariable;
use Preflight\Core\Operations\Unknown;
use Preflight\Core\Schema\Index;
use Preflight\Core\Sql\ParsedStatement;
use Preflight\Core\Sql\StatementParser;

it('splits a pretend script into statements', function () {
    $statements = (new StatementParser)->parse("alter table `a` add `x` int null;\nalter table `b` drop `y`;\n-- comment\nselect 1");

    expect($statements)->toHaveCount(3)
        ->and($statements[0]->table)->toBe('a')
        ->and($statements[2]->kind)->toBe(ParsedStatement::IGNORED);
});

it('reads a Laravel column definition with position', function () {
    [$statement] = (new StatementParser)->parse("alter table `orders` add `note` varchar(255) null default 'x' comment 'hi' after `id`");
    $op = $statement->operations[0];

    expect($op)->toBeInstanceOf(AddColumn::class);
    assert($op instanceof AddColumn);
    expect($op->column->type)->toBe('VARCHAR')
        ->and($op->column->params)->toBe(['255'])
        ->and($op->column->nullable)->toBeTrue()
        ->and($op->column->default)->toBe("'x'")
        ->and($op->column->comment)->toBe('hi')
        ->and($op->position)->toBe('AFTER')
        ->and($op->after)->toBe('id');
});

it('reads CHANGE as a rename plus definition', function () {
    [$statement] = (new StatementParser)->parse('alter table `t` change `old` `new` bigint unsigned not null first');
    $op = $statement->operations[0];

    expect($op)->toBeInstanceOf(ModifyColumn::class);
    assert($op instanceof ModifyColumn);
    expect($op->from)->toBe('old')
        ->and($op->column->name)->toBe('new')
        ->and($op->column->unsigned)->toBeTrue()
        ->and($op->position)->toBe('FIRST')
        ->and($op->renames())->toBeTrue();
});

it('reads index types and column lists', function () {
    [$statement] = (new StatementParser)->parse('alter table `t` add unique `u`(`a`, `b`(10) desc), add fulltext `f`(`body`), add primary key (`id`)');
    $types = array_map(static fn (AddIndex $op) => [$op->index->type, $op->index->name, $op->index->columns], $statement->operations);

    expect($types)->toBe([
        [Index::UNIQUE, 'u', ['a', 'b']],
        [Index::FULLTEXT, 'f', ['body']],
        [Index::PRIMARY, 'PRIMARY', ['id']],
    ]);
});

it('reads foreign keys with the referenced table', function () {
    [$statement] = (new StatementParser)->parse('alter table `orders` add constraint `fk` foreign key (`user_id`) references `users` (`id`) on delete cascade');
    $op = $statement->operations[0];

    expect($op)->toBeInstanceOf(AddForeignKey::class);
    assert($op instanceof AddForeignKey);
    expect($op->name)->toBe('fk')->and($op->columns)->toBe(['user_id'])->and($op->references)->toBe('users');
});

it('captures explicit ALGORITHM and LOCK', function () {
    [$statement] = (new StatementParser)->parse('alter table `t` add index `i`(`a`), algorithm=inplace, lock=none');

    expect($statement->explicitAlgorithm)->toBe('INPLACE')
        ->and($statement->explicitLock)->toBe('NONE')
        ->and($statement->operations)->toHaveCount(1);
});

it('reads multi-table DROP and RENAME', function () {
    [$drop, $rename] = (new StatementParser)->parse('drop table if exists `a`, `b`; rename table `c` to `d`, `e` to `f`');

    expect(array_map(static fn (DropTable $op) => $op->table, $drop->operations))->toBe(['a', 'b'])
        ->and(array_map(static fn (RenameTable $op) => [$op->table, $op->to], $rename->operations))->toBe([['c', 'd'], ['e', 'f']]);
});

it('reads schema-qualified table names', function () {
    [$statement] = (new StatementParser)->parse('alter table `app`.`orders` add `x` int null');

    expect($statement->table)->toBe('orders');
});

it('reads SET foreign_key_checks in its common spellings', function (string $sql) {
    [$statement] = (new StatementParser)->parse($sql);
    $op = $statement->operations[0];

    expect($op)->toBeInstanceOf(SetVariable::class);
    assert($op instanceof SetVariable);
    expect($op->name)->toBe('foreign_key_checks')->and($op->value)->toBe('0');
})->with([
    'SET FOREIGN_KEY_CHECKS=0',
    'set foreign_key_checks = 0',
    'SET SESSION foreign_key_checks=0',
    'SET @@session.foreign_key_checks = 0',
]);

it('marks anything unrecognised as unknown instead of dropping it', function () {
    [$statement] = (new StatementParser)->parse('alter table `t` add `x` int null, partition by hash(id)');

    expect($statement->operations)->toHaveCount(2)
        ->and($statement->operations[1])->toBeInstanceOf(Unknown::class);
});
