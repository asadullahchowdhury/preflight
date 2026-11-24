<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * How MySQL executes one ALTER clause (or a whole statement, once combined).
 */
final class Classification
{
    /**
     * @param  bool  $rebuildIfNotInstant  The clause is INSTANT on its own, but rebuilds the table when the
     *                                     statement as a whole cannot run INSTANT (e.g. ADD COLUMN + ADD INDEX).
     */
    public function __construct(
        public readonly Algorithm $algorithm,
        public readonly LockLevel $lock,
        public readonly Cost $cost,
        public readonly bool $rebuildIfNotInstant = false,
    ) {}

    public static function instant(bool $rebuildIfNotInstant = false): self
    {
        return new self(Algorithm::Instant, LockLevel::None, Cost::Metadata, $rebuildIfNotInstant);
    }

    public static function inplaceMetadata(): self
    {
        return new self(Algorithm::Inplace, LockLevel::None, Cost::Metadata);
    }

    public static function inplaceIndexBuild(LockLevel $lock = LockLevel::None): self
    {
        return new self(Algorithm::Inplace, $lock, Cost::IndexBuild);
    }

    public static function inplaceRebuild(LockLevel $lock = LockLevel::None): self
    {
        return new self(Algorithm::Inplace, $lock, Cost::TableRebuild);
    }

    public static function copy(): self
    {
        return new self(Algorithm::Copy, LockLevel::Shared, Cost::TableRebuild);
    }

    public function rebuildsTable(): bool
    {
        return $this->cost === Cost::TableRebuild;
    }

    public function blocksWrites(): bool
    {
        return $this->lock->blocksWrites();
    }

    /**
     * Combine clauses of one ALTER statement: MySQL runs the statement with the most expensive algorithm
     * any clause needs, and the strongest lock.
     *
     * @param  list<self>  $parts
     */
    public static function combine(array $parts): ?self
    {
        if ($parts === []) {
            return null;
        }

        $algorithm = Algorithm::Instant;
        $lock = LockLevel::None;
        $cost = Cost::Metadata;

        foreach ($parts as $part) {
            $algorithm = $algorithm->max($part->algorithm);
            $lock = $lock->max($part->lock);
            $cost = $cost->max($part->cost);
        }

        if ($algorithm !== Algorithm::Instant) {
            foreach ($parts as $part) {
                if ($part->rebuildIfNotInstant) {
                    $cost = Cost::TableRebuild;
                }
            }
        }

        if ($algorithm === Algorithm::Copy) {
            $lock = $lock->max(LockLevel::Shared);
            $cost = Cost::TableRebuild;
        }

        return new self($algorithm, $lock, $cost);
    }
}
