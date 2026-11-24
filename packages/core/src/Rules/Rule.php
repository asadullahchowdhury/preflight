<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Operations\Operation;

interface Rule
{
    public function supports(Operation $operation): bool;

    public function evaluate(Operation $operation, RuleContext $context): Verdict;
}
