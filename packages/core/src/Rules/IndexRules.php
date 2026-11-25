<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Operations\AddIndex;
use Preflight\Core\Operations\AlterIndexVisibility;
use Preflight\Core\Operations\DropIndex;
use Preflight\Core\Operations\DropPrimaryKey;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Operations\RenameIndex;
use Preflight\Core\Report\Classification;
use Preflight\Core\Report\LockLevel;
use Preflight\Core\Schema\Index;

final class IndexRules implements Rule
{
    public function supports(Operation $operation): bool
    {
        return $operation instanceof AddIndex
            || $operation instanceof DropIndex
            || $operation instanceof DropPrimaryKey
            || $operation instanceof RenameIndex
            || $operation instanceof AlterIndexVisibility;
    }

    public function evaluate(Operation $operation, RuleContext $context): Verdict
    {
        return match (true) {
            $operation instanceof AddIndex => $this->add($operation, $context),
            $operation instanceof DropPrimaryKey => $this->dropPrimary($context),
            $operation instanceof DropIndex => new Verdict('drop-index', Classification::inplaceMetadata(),
                "Drops index `{$operation->name}` in place (metadata only).",
                'Queries relying on this index may get slower immediately; check them first.'),
            $operation instanceof RenameIndex => new Verdict('rename-index', Classification::inplaceMetadata(),
                "Renames index `{$operation->from}` in place (metadata only)."),
            $operation instanceof AlterIndexVisibility => new Verdict('index-visibility', Classification::inplaceMetadata(),
                "Makes index `{$operation->name}` ".($operation->visible ? 'visible' : 'invisible').' (metadata only).'),
            default => throw new \LogicException('Unsupported operation'),
        };
    }

    private function add(AddIndex $op, RuleContext $context): Verdict
    {
        $table = $context->table;
        $index = $op->index;

        switch ($index->type) {
            case Index::PRIMARY:
                $nullable = [];
                foreach ($index->columns as $name) {
                    $column = $table?->column($name);
                    if ($column !== null && $column->nullable) {
                        $nullable[] = $name;
                    }
                }
                if ($nullable !== []) {
                    return new Verdict('add-primary-key', Classification::copy(),
                        'Adding a primary key on nullable column(s) `'.implode('`, `', $nullable).'` forces ALGORITHM=COPY and blocks writes.',
                        Advice::blocking('Make the column(s) NOT NULL in an earlier step.'));
                }

                return new Verdict('add-primary-key', Classification::inplaceRebuild(),
                    'Adding a primary key rebuilds the table in place (rows are re-clustered); writes continue.',
                    ColumnRules::online($context));

            case Index::FULLTEXT:
                $first = $table === null || ! $table->hasFulltextIndex();

                return new Verdict('add-fulltext-index',
                    $first ? Classification::inplaceRebuild(LockLevel::Shared) : Classification::inplaceIndexBuild(LockLevel::Shared),
                    'Building a FULLTEXT index blocks writes (LOCK=SHARED) for the whole build'.($first ? ', and the first one on a table also rebuilds it.' : '.'),
                    Advice::blocking('Run it in a maintenance window.'));

            case Index::SPATIAL:
                return new Verdict('add-spatial-index', Classification::inplaceIndexBuild(LockLevel::Shared),
                    'Building a SPATIAL index blocks writes (LOCK=SHARED) for the whole build.',
                    Advice::blocking('Run it in a maintenance window.'));

            default:
                $kind = $index->type === Index::UNIQUE ? 'unique index' : 'index';
                $suggestion = ColumnRules::online($context);
                if ($index->type === Index::UNIQUE) {
                    $columns = implode(', ', $index->columns);
                    $suggestion .= ' The build fails (and rolls back) if duplicates exist or are written during it; check first: '
                        ."`SELECT {$columns}, COUNT(*) FROM {$context->statement->table} GROUP BY {$columns} HAVING COUNT(*) > 1`.";
                }

                return new Verdict($index->type === Index::UNIQUE ? 'add-unique-index' : 'add-index',
                    Classification::inplaceIndexBuild(),
                    "Builds the {$kind} `{$index->name}` online (INPLACE, LOCK=NONE): the table is scanned and sorted, writes continue.",
                    $suggestion);
        }
    }

    private function dropPrimary(RuleContext $context): Verdict
    {
        $replaced = $context->statementHas(AddIndex::class, static fn (AddIndex $op) => $op->index->type === Index::PRIMARY);

        if ($replaced) {
            return new Verdict('drop-primary-key', Classification::inplaceRebuild(),
                'Replacing the primary key in the same statement rebuilds the table in place; writes continue.',
                Advice::OFF_PEAK);
        }

        return new Verdict('drop-primary-key', Classification::copy(),
            'Dropping the primary key without adding a new one in the same statement forces ALGORITHM=COPY and blocks writes.',
            'Drop and add the primary key in one ALTER TABLE statement so MySQL can do it in place.');
    }
}
