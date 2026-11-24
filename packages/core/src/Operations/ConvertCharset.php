<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class ConvertCharset extends Operation
{
    public function __construct(string $table, public readonly string $charset, public readonly ?string $collation = null)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'CONVERT TO CHARACTER SET '.$this->charset.($this->collation !== null ? ' COLLATE '.$this->collation : '');
    }
}
