<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Report\Classification;
use Preflight\Core\Report\Severity;

/**
 * What a rule concludes about one operation, before severity is decided by the analyzer.
 */
final class Verdict
{
    /**
     * @param  ?Classification  $classification  Null for statements that aren't online DDL (DROP TABLE, DML, unknown…).
     * @param  ?Severity  $fixedSeverity  Overrides the severity derived from the classification.
     * @param  bool  $destructive  Loses data or breaks running code; at least `warn` even when instant.
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly ?Classification $classification,
        public readonly string $message,
        public readonly ?string $suggestion = null,
        public readonly ?Severity $fixedSeverity = null,
        public readonly bool $destructive = false,
    ) {}
}
