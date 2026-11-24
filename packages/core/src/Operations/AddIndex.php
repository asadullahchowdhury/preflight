<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

use Preflight\Core\Schema\Index;

final class AddIndex extends Operation
{
    public function __construct(string $table, public readonly Index $index)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        $type = match ($this->index->type) {
            Index::PRIMARY => 'PRIMARY KEY',
            Index::UNIQUE => 'UNIQUE INDEX',
            Index::FULLTEXT => 'FULLTEXT INDEX',
            Index::SPATIAL => 'SPATIAL INDEX',
            default => 'INDEX',
        };
        $name = $this->index->type === Index::PRIMARY ? '' : ' '.self::q($this->index->name);

        return 'ADD '.$type.$name.' ('.implode(', ', array_map(self::q(...), $this->index->columns)).')';
    }
}
