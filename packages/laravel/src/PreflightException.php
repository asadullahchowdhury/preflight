<?php

declare(strict_types=1);

namespace Preflight\Laravel;

use RuntimeException;

/**
 * A problem the user can fix (wrong driver, unknown git ref…), reported without a stack trace.
 */
final class PreflightException extends RuntimeException {}
