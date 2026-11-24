<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class AddForeignKey extends Operation
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(string $table, public readonly string $name, public readonly array $columns, public readonly ?string $references = null)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'ADD FOREIGN KEY '.self::q($this->name).' ('.implode(', ', array_map(self::q(...), $this->columns)).')'
            .($this->references !== null ? ' → '.self::q($this->references) : '');
    }
}
