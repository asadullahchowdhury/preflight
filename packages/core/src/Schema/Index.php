<?php

declare(strict_types=1);

namespace Preflight\Core\Schema;

final class Index
{
    public const PRIMARY = 'PRIMARY';

    public const UNIQUE = 'UNIQUE';

    public const INDEX = 'INDEX';

    public const FULLTEXT = 'FULLTEXT';

    public const SPATIAL = 'SPATIAL';

    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $columns,
    ) {}

    public function covers(string $column): bool
    {
        foreach ($this->columns as $c) {
            if (strcasecmp($c, $column) === 0) {
                return true;
            }
        }

        return false;
    }

    public function withName(string $name): self
    {
        return new self($name, $this->type, $this->columns);
    }

    /**
     * @param  callable(string): string  $rename
     */
    public function mapColumns(callable $rename): self
    {
        return new self($this->name, $this->type, array_values(array_map($rename, $this->columns)));
    }
}
