<?php

declare(strict_types=1);

namespace Preflight\Core\Server;

use InvalidArgumentException;

/**
 * The MySQL server the migration will run on, and the session settings that change DDL behaviour.
 */
final class ServerProfile
{
    private function __construct(
        public readonly string $version,
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
        public readonly bool $foreignKeyChecks,
        public readonly string $defaultCharset,
    ) {}

    public static function mysql(string $version, bool $foreignKeyChecks = true, string $defaultCharset = 'utf8mb4'): self
    {
        if (! preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?/', trim($version), $m)) {
            throw new InvalidArgumentException("Unrecognised MySQL version [{$version}].");
        }

        $major = (int) $m[1];
        $minor = (int) $m[2];
        $patch = (int) ($m[3] ?? 0);

        if ($major < 8) {
            throw new InvalidArgumentException("MySQL {$version} is not supported; Preflight supports MySQL 8.0 and later.");
        }

        return new self("{$major}.{$minor}.{$patch}", $major, $minor, $patch, $foreignKeyChecks, strtolower($defaultCharset));
    }

    public function atLeast(string $version): bool
    {
        return version_compare($this->version, $version, '>=');
    }

    public function withForeignKeyChecks(bool $enabled): self
    {
        return new self($this->version, $this->major, $this->minor, $this->patch, $enabled, $this->defaultCharset);
    }

    /** INSTANT ADD COLUMN (last position only) and other INSTANT metadata changes: 8.0.12+. */
    public function supportsInstant(): bool
    {
        return $this->atLeast('8.0.12');
    }

    /** INSTANT ADD COLUMN at any position and INSTANT DROP COLUMN: 8.0.29+. */
    public function supportsInstantAnyPosition(): bool
    {
        return $this->atLeast('8.0.29');
    }

    /** INSTANT RENAME COLUMN: 8.0.28+. */
    public function supportsInstantRenameColumn(): bool
    {
        return $this->atLeast('8.0.28');
    }
}
