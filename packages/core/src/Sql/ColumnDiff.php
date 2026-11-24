<?php

declare(strict_types=1);

namespace Preflight\Core\Sql;

use Preflight\Core\Report\Classification;
use Preflight\Core\Schema\Charset;
use Preflight\Core\Schema\Column;
use Preflight\Core\Schema\Table;
use Preflight\Core\Server\ServerProfile;

/**
 * Compares a column's current definition with the one a MODIFY/CHANGE writes, and decides how MySQL
 * executes the change. Laravel's ->change() always restates the full definition, so most "changes"
 * are really one attribute changing; this finds which one.
 */
final class ColumnDiff
{
    /** @var list<string> */
    public readonly array $changes;

    public readonly Classification $classification;

    /** The change that decided the classification, for messages. */
    public readonly string $reason;

    public function __construct(
        Column $old,
        Column $new,
        Table $table,
        ServerProfile $server,
        bool $moves = false,
    ) {
        $changes = [];
        /** @var list<array{0: Classification, 1: string}> $parts */
        $parts = [];

        $metadata = $server->supportsInstant() ? Classification::instant() : Classification::inplaceMetadata();

        // Rename (CHANGE old new …).
        if (strcasecmp($old->name, $new->name) !== 0) {
            $changes[] = "renamed `{$old->name}` → `{$new->name}`";
            $parts[] = [$server->supportsInstantRenameColumn() ? Classification::instant() : Classification::inplaceMetadata(), 'rename'];
        }

        // Generated column expression or storage.
        if ($old->generatedExpression !== $new->generatedExpression || $old->generatedStored !== $new->generatedStored) {
            $changes[] = 'generated column definition changed';
            $parts[] = [Classification::copy(), 'generated column change'];
        }

        // Data type.
        $typeChange = $this->typeChange($old, $new, $table, $server);
        if ($typeChange !== null) {
            [$classification, $description] = $typeChange;
            $changes[] = $description;
            $parts[] = [$classification, $description];
        }

        // Character set / collation.
        $oldCharset = $old->isTextual() ? $table->effectiveCharset($old, $server->defaultCharset) : null;
        $newCharset = $new->isTextual() ? $table->effectiveCharset($new, $server->defaultCharset) : null;
        if ($oldCharset !== null && $newCharset !== null && $oldCharset !== $newCharset) {
            $changes[] = "character set {$oldCharset} → {$newCharset}";
            $parts[] = [Classification::copy(), 'character set change'];
        } elseif ($old->isTextual() && $new->isTextual()) {
            $oldCollation = $this->effectiveCollation($old, $table, $server);
            $newCollation = $this->effectiveCollation($new, $table, $server);
            if ($oldCollation !== null && $newCollation !== null && $oldCollation !== $newCollation) {
                $changes[] = "collation {$oldCollation} → {$newCollation}";
                // Verified on 8.0.30 for non-indexed columns; indexed columns must re-sort the index.
                $parts[] = [$table->isColumnIndexed($old->name) ? Classification::copy() : Classification::inplaceMetadata(), 'collation change'];
            }
        }

        // NULL / NOT NULL.
        if ($old->nullable !== $new->nullable) {
            $changes[] = $new->nullable ? 'NOT NULL → NULL' : 'NULL → NOT NULL';
            $parts[] = [Classification::inplaceRebuild(), 'nullability change'];
        }

        // AUTO_INCREMENT.
        if ($old->autoIncrement !== $new->autoIncrement) {
            $changes[] = $new->autoIncrement ? 'AUTO_INCREMENT added' : 'AUTO_INCREMENT removed';
            $parts[] = [Classification::copy(), 'AUTO_INCREMENT change'];
        }

        // Metadata-only attributes.
        if ($old->default !== $new->default) {
            $changes[] = 'default '.($old->default ?? 'none').' → '.($new->default ?? 'none');
            $parts[] = [$metadata, 'default change'];
        }
        if ($old->comment !== $new->comment) {
            $changes[] = 'comment changed';
            $parts[] = [$metadata, 'comment change'];
        }
        if ($old->onUpdate !== $new->onUpdate) {
            $changes[] = 'ON UPDATE changed';
            $parts[] = [$metadata, 'ON UPDATE change'];
        }
        if ($old->invisible !== $new->invisible) {
            $changes[] = $new->invisible ? 'made INVISIBLE' : 'made VISIBLE';
            $parts[] = [$metadata, 'visibility change'];
        }

        // Moving the column (FIRST / AFTER).
        if ($moves) {
            $changes[] = 'column moved';
            $parts[] = [Classification::inplaceRebuild(), 'column reorder'];
        }

        if ($parts === []) {
            $parts[] = [$metadata, 'no effective change'];
        }

        $classification = Classification::combine(array_map(static fn (array $p) => $p[0], $parts));
        assert($classification !== null);

        $reason = $parts[0][1];
        foreach ($parts as [$part, $description]) {
            if ($part->algorithm === $classification->algorithm && $part->cost === $classification->cost) {
                $reason = $description;
                break;
            }
        }

        $this->changes = $changes;
        $this->classification = $classification;
        $this->reason = $reason;
    }

    /**
     * @return array{0: Classification, 1: string}|null
     */
    private function typeChange(Column $old, Column $new, Table $table, ServerProfile $server): ?array
    {
        $label = $old->typeLabel().' → '.$new->typeLabel();

        if ($old->unsigned !== $new->unsigned) {
            return [Classification::copy(), $label];
        }

        if ($old->type !== $new->type) {
            return [Classification::copy(), $label];
        }

        // Integer display widths (int(11) vs int) are cosmetic.
        if ($old->isInteger()) {
            return null;
        }

        if ($old->params === $new->params) {
            return null;
        }

        if ($old->isVarLength()) {
            $oldLength = (int) $old->length();
            $newLength = (int) $new->length();
            $charset = $old->type === 'VARBINARY' ? 'binary' : $table->effectiveCharset($old, $server->defaultCharset);
            $bytesPerChar = Charset::maxBytesPerChar($charset);
            $oldLengthBytes = $oldLength * $bytesPerChar > 255 ? 2 : 1;
            $newLengthBytes = $newLength * $bytesPerChar > 255 ? 2 : 1;

            if ($newLength < $oldLength) {
                return [Classification::copy(), $label.' (shrinking)'];
            }
            if ($oldLengthBytes !== $newLengthBytes) {
                return [Classification::copy(), $label.' (crosses the 255-byte length boundary)'];
            }

            return [Classification::inplaceMetadata(), $label];
        }

        if ($old->type === 'ENUM' || $old->type === 'SET') {
            $appended = count($new->params) >= count($old->params)
                && array_slice($new->params, 0, count($old->params)) === $old->params;
            if ($appended && self::enumStorage($old) === self::enumStorage($new)) {
                return [$server->supportsInstant() ? Classification::instant() : Classification::inplaceMetadata(), $label.' (members appended)'];
            }

            return [Classification::copy(), $label];
        }

        return [Classification::copy(), $label];
    }

    private function effectiveCollation(Column $column, Table $table, ServerProfile $server): ?string
    {
        if ($column->collation !== null) {
            return strtolower($column->collation);
        }
        if ($column->charset !== null) {
            return Charset::defaultCollation($column->charset);
        }
        if ($table->collation !== null) {
            return strtolower($table->collation);
        }

        return Charset::defaultCollation($table->charset ?? $server->defaultCharset);
    }

    private static function enumStorage(Column $column): int
    {
        $members = count($column->params);
        if ($column->type === 'ENUM') {
            return $members <= 255 ? 1 : 2;
        }

        return match (true) {
            $members <= 8 => 1,
            $members <= 16 => 2,
            $members <= 24 => 3,
            $members <= 32 => 4,
            default => 8,
        };
    }
}
