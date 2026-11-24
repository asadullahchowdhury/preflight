<?php

declare(strict_types=1);

namespace Preflight\Core\Sql;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use Preflight\Core\Operations\AddCheck;
use Preflight\Core\Operations\AddColumn;
use Preflight\Core\Operations\AddForeignKey;
use Preflight\Core\Operations\AddIndex;
use Preflight\Core\Operations\AlterColumn;
use Preflight\Core\Operations\AlterIndexVisibility;
use Preflight\Core\Operations\ConvertCharset;
use Preflight\Core\Operations\CreateTable;
use Preflight\Core\Operations\DataChange;
use Preflight\Core\Operations\DropColumn;
use Preflight\Core\Operations\DropForeignKey;
use Preflight\Core\Operations\DropIndex;
use Preflight\Core\Operations\DropPrimaryKey;
use Preflight\Core\Operations\DropTable;
use Preflight\Core\Operations\Ignored;
use Preflight\Core\Operations\ModifyColumn;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Operations\RenameColumn;
use Preflight\Core\Operations\RenameIndex;
use Preflight\Core\Operations\RenameTable;
use Preflight\Core\Operations\SetVariable;
use Preflight\Core\Operations\TableOption;
use Preflight\Core\Operations\TruncateTable;
use Preflight\Core\Operations\Unknown;
use Preflight\Core\Schema\DefinitionReader;
use Preflight\Core\Schema\Index;

/**
 * Turns SQL text (as produced by `php artisan migrate --pretend`) into typed operations.
 *
 * phpmyadmin/sql-parser does the lexing and CREATE TABLE / column-definition parsing; ALTER clause
 * structure is matched here so every clause maps to exactly one operation (or an explicit Unknown).
 */
final class StatementParser
{
    private const TABLE_OPTIONS = [
        'ENGINE', 'ROW_FORMAT', 'KEY_BLOCK_SIZE', 'COMMENT', 'AUTO_INCREMENT', 'STATS_PERSISTENT',
        'STATS_AUTO_RECALC', 'STATS_SAMPLE_PAGES', 'AVG_ROW_LENGTH', 'MAX_ROWS', 'MIN_ROWS', 'PACK_KEYS',
        'CHECKSUM', 'COMPRESSION', 'ENCRYPTION', 'DELAY_KEY_WRITE', 'INSERT_METHOD', 'TABLESPACE',
    ];

    private const IGNORED_VERBS = [
        'SELECT', 'SHOW', 'USE', 'BEGIN', 'START', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'RELEASE', 'DESCRIBE',
        'DESC', 'EXPLAIN', 'WITH', 'DO', 'ANALYZE', 'CHECK', 'CHECKSUM', 'FLUSH', 'GRANT', 'REVOKE', 'CALL',
    ];

    /**
     * @return list<ParsedStatement>
     */
    public function parse(string $sql): array
    {
        $statements = [];
        foreach (TokenList::statements($sql) as $tokens) {
            $statements[] = $this->statement($tokens);
        }

        return $statements;
    }

    public function statement(TokenList $t): ParsedStatement
    {
        $text = $t->text();
        $verb = $t->at(0)?->upper() ?? '';

        return match (true) {
            $verb === 'ALTER' => $this->alter($t, $text),
            $verb === 'CREATE' => $this->create($t, $text),
            $verb === 'DROP' => $this->drop($t, $text),
            $verb === 'RENAME' => $this->renameTable($t, $text),
            $verb === 'TRUNCATE' => $this->truncate($t, $text),
            in_array($verb, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true) => $this->dml($t, $text, $verb),
            $verb === 'SET' => $this->set($text),
            in_array($verb, self::IGNORED_VERBS, true) => new ParsedStatement($text, ParsedStatement::IGNORED, null, [new Ignored($verb)]),
            default => $this->unknown($text, null, 'Statement type is not recognised.'),
        };
    }

    // ---------------------------------------------------------------- ALTER TABLE

    private function alter(TokenList $t, string $text): ParsedStatement
    {
        $i = 1;
        while ($t->isWord($i, 'ONLINE', 'OFFLINE', 'IGNORE')) {
            $i++;
        }
        if (! $t->isWord($i, 'TABLE')) {
            return $this->unknown($text, null, 'Only ALTER TABLE is analysed.');
        }
        [$table, $i] = $t->name($i + 1);
        if ($table === null) {
            return $this->unknown($text, null, 'Could not read the table name.');
        }

        $operations = [];
        $algorithm = null;
        $lock = null;

        foreach ($t->splitTopLevel($i) as $clause) {
            $operation = $this->alterClause($clause, $table, $algorithm, $lock);
            if ($operation !== null) {
                $operations[] = $operation;
            }
        }

        return new ParsedStatement($text, ParsedStatement::ALTER, $table, $operations, $algorithm, $lock);
    }

    private function alterClause(TokenList $c, string $table, ?string &$algorithm, ?string &$lock): ?Operation
    {
        $verb = $c->at(0)?->kind === Tok::WORD ? $c->at(0)->upper() : '';

        switch ($verb) {
            case 'ADD':
                return $this->add($c, $table);
            case 'DROP':
                return $this->dropClause($c, $table);
            case 'MODIFY':
                $i = $c->isWord(1, 'COLUMN') ? 2 : 1;
                $from = $c->at($i)?->value;

                return $from === null
                    ? new Unknown($table, $c->text(), 'Could not read MODIFY column.')
                    : $this->columnDefinition($c, $i, $table, $from, false);
            case 'CHANGE':
                $i = $c->isWord(1, 'COLUMN') ? 2 : 1;
                $from = $c->at($i)?->value;

                return $from === null
                    ? new Unknown($table, $c->text(), 'Could not read CHANGE column.')
                    : $this->columnDefinition($c, $i + 1, $table, $from, true);
            case 'RENAME':
                return $this->renameClause($c, $table);
            case 'ALTER':
                return $this->alterColumnClause($c, $table);
            case 'CONVERT':
                $i = $c->matches(1, 'TO', 'CHARACTER', 'SET') ?? $c->matches(1, 'TO', 'CHARSET');
                if ($i === null || $c->at($i) === null) {
                    return new Unknown($table, $c->text(), 'Unrecognised CONVERT clause.');
                }
                $charset = (string) $c->at($i)->value;
                $collation = $c->isWord($i + 1, 'COLLATE') ? $c->at($c->skipEquals($i + 2))?->value : null;

                return new ConvertCharset($table, $charset, $collation);
            case 'ALGORITHM':
                $algorithm = strtoupper((string) $c->at($c->skipEquals(1))?->value);

                return null;
            case 'LOCK':
                $lock = strtoupper((string) $c->at($c->skipEquals(1))?->value);

                return null;
            case 'FORCE':
                return new TableOption($table, 'FORCE');
            case 'DEFAULT':
                return $this->charsetOption($c, 1, $table);
            case 'CHARACTER':
            case 'CHARSET':
            case 'COLLATE':
                return $this->charsetOption($c, 0, $table);
        }

        if (in_array($verb, self::TABLE_OPTIONS, true)) {
            $value = $c->at($c->skipEquals(1));

            return new TableOption($table, $verb, $value?->value);
        }

        return new Unknown($table, $c->text(), 'Unrecognised ALTER TABLE clause.');
    }

    private function add(TokenList $c, string $table): Operation
    {
        $i = 1;
        if ($c->isWord($i, 'COLUMN')) {
            return $c->at($i + 1)?->isPunct('(')
                ? new Unknown($table, $c->text(), 'ADD COLUMN with a parenthesised list is not analysed.')
                : $this->columnDefinition($c, $i + 1, $table, null, false);
        }
        if ($c->at($i)?->isPunct('(')) {
            return new Unknown($table, $c->text(), 'ADD with a parenthesised column list is not analysed.');
        }

        $constraint = null;
        if ($c->isWord($i, 'CONSTRAINT')) {
            $i++;
            $next = $c->at($i);
            if ($next !== null && $next->isName() && ! $next->isWord('PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK')) {
                $constraint = $next->value;
                $i++;
            }
        }

        if ($c->isWord($i, 'PRIMARY')) {
            [$columns] = $this->indexColumns($c, $i + 2);

            return new AddIndex($table, new Index('PRIMARY', Index::PRIMARY, $columns));
        }

        if ($c->isWord($i, 'UNIQUE', 'FULLTEXT', 'SPATIAL', 'INDEX', 'KEY')) {
            $type = match ($c->at($i)?->upper()) {
                'UNIQUE' => Index::UNIQUE,
                'FULLTEXT' => Index::FULLTEXT,
                'SPATIAL' => Index::SPATIAL,
                default => Index::INDEX,
            };
            if ($type !== Index::INDEX) {
                $i++;
            }
            if ($c->isWord($i, 'INDEX', 'KEY')) {
                $i++;
            }
            $name = null;
            $tok = $c->at($i);
            if ($tok !== null && $tok->isName() && ! $tok->isWord('USING')) {
                $name = $tok->value;
                $i++;
            }
            [$columns] = $this->indexColumns($c, $i);

            return new AddIndex($table, new Index($name ?? $constraint ?? ($columns[0] ?? 'index'), $type, $columns));
        }

        if ($c->isWord($i, 'FOREIGN')) {
            $i += $c->isWord($i + 1, 'KEY') ? 2 : 1;
            $tok = $c->at($i);
            if ($tok !== null && $tok->isName()) {
                $constraint ??= $tok->value;
                $i++;
            }
            [$columns, $i] = $this->indexColumns($c, $i);
            $references = null;
            if ($c->isWord($i, 'REFERENCES')) {
                [$references] = $c->name($i + 1);
            }

            return new AddForeignKey($table, $constraint ?? $table.'_ibfk', $columns, $references);
        }

        if ($c->isWord($i, 'CHECK')) {
            return new AddCheck($table, $constraint);
        }

        if ($constraint !== null) {
            return new Unknown($table, $c->text(), 'Unrecognised constraint.');
        }

        return $this->columnDefinition($c, $i, $table, null, false);
    }

    /**
     * @return array{0: list<string>, 1: int}
     */
    private function indexColumns(TokenList $c, int $i): array
    {
        if ($c->isWord($i, 'USING')) {
            $i += 2;
        }
        if (! $c->at($i)?->isPunct('(')) {
            return [[], $i];
        }

        return $c->columnList($i);
    }

    private function columnDefinition(TokenList $c, int $nameAt, string $table, ?string $from, bool $viaChange): Operation
    {
        $end = $c->count() - 1;
        $position = null;
        $after = null;

        $last = $c->at($end);
        if ($last !== null && $last->isWord('FIRST')) {
            $position = 'FIRST';
            $end--;
        } elseif ($last !== null && $end - 1 > $nameAt && $c->isWord($end - 1, 'AFTER') && $last->isName()) {
            $position = 'AFTER';
            $after = $last->value;
            $end -= 2;
        }

        $column = DefinitionReader::column($c->text($nameAt, $end));
        if ($column === null) {
            return new Unknown($table, $c->text(), 'Could not parse the column definition.');
        }

        if ($from === null) {
            return new AddColumn($table, $column, $position, $after);
        }

        return new ModifyColumn($table, $from, $column, $position, $after, $viaChange);
    }

    private function dropClause(TokenList $c, string $table): Operation
    {
        if ($c->isWord(1, 'COLUMN')) {
            [$name] = $c->name(2);

            return $name !== null ? new DropColumn($table, $name) : new Unknown($table, $c->text(), 'Could not read column name.');
        }
        if ($c->isWord(1, 'INDEX', 'KEY')) {
            [$name] = $c->name(2);

            return $name !== null ? new DropIndex($table, $name) : new Unknown($table, $c->text(), 'Could not read index name.');
        }
        if ($c->matches(1, 'PRIMARY', 'KEY') !== null) {
            return new DropPrimaryKey($table);
        }
        if ($c->matches(1, 'FOREIGN', 'KEY') !== null) {
            [$name] = $c->name(3);

            return $name !== null ? new DropForeignKey($table, $name) : new Unknown($table, $c->text(), 'Could not read foreign key name.');
        }
        if ($c->isWord(1, 'CONSTRAINT', 'CHECK', 'PARTITION')) {
            return new Unknown($table, $c->text(), 'DROP '.$c->at(1)?->upper().' is not analysed yet.');
        }

        [$name] = $c->name(1);

        return $name !== null ? new DropColumn($table, $name) : new Unknown($table, $c->text(), 'Unrecognised DROP clause.');
    }

    private function renameClause(TokenList $c, string $table): Operation
    {
        if ($c->isWord(1, 'COLUMN', 'INDEX', 'KEY')) {
            [$from, $i] = $c->name(2);
            [$to] = $c->isWord($i, 'TO') ? $c->name($i + 1) : [null, $i];
            if ($from === null || $to === null) {
                return new Unknown($table, $c->text(), 'Could not read RENAME clause.');
            }

            return $c->isWord(1, 'COLUMN') ? new RenameColumn($table, $from, $to) : new RenameIndex($table, $from, $to);
        }

        $i = $c->isWord(1, 'TO', 'AS') ? 2 : 1;
        [$to] = $c->name($i);

        return $to !== null ? new RenameTable($table, $to) : new Unknown($table, $c->text(), 'Could not read new table name.');
    }

    private function alterColumnClause(TokenList $c, string $table): Operation
    {
        if ($c->isWord(1, 'INDEX', 'KEY')) {
            [$name, $i] = $c->name(2);
            if ($name !== null && $c->isWord($i, 'VISIBLE', 'INVISIBLE')) {
                return new AlterIndexVisibility($table, $name, $c->isWord($i, 'VISIBLE'));
            }

            return new Unknown($table, $c->text(), 'Unrecognised ALTER INDEX clause.');
        }
        if ($c->isWord(1, 'CHECK', 'CONSTRAINT')) {
            return new Unknown($table, $c->text(), 'ALTER CHECK/CONSTRAINT is not analysed yet.');
        }

        $i = $c->isWord(1, 'COLUMN') ? 2 : 1;
        [$column, $i] = $c->name($i);
        if ($column === null) {
            return new Unknown($table, $c->text(), 'Could not read column name.');
        }

        if ($c->matches($i, 'SET', 'DEFAULT') !== null) {
            $value = DefinitionReader::normalizeDefault($c->text($i + 2));

            return new AlterColumn($table, $column, AlterColumn::SET_DEFAULT, $value);
        }
        if ($c->matches($i, 'DROP', 'DEFAULT') !== null) {
            return new AlterColumn($table, $column, AlterColumn::DROP_DEFAULT);
        }
        if ($c->matches($i, 'SET', 'VISIBLE') !== null) {
            return new AlterColumn($table, $column, AlterColumn::SET_VISIBLE);
        }
        if ($c->matches($i, 'SET', 'INVISIBLE') !== null) {
            return new AlterColumn($table, $column, AlterColumn::SET_INVISIBLE);
        }

        return new Unknown($table, $c->text(), 'Unrecognised ALTER COLUMN clause.');
    }

    private function charsetOption(TokenList $c, int $i, string $table): Operation
    {
        if ($c->isWord($i, 'COLLATE')) {
            return new TableOption($table, 'COLLATE', $c->at($c->skipEquals($i + 1))?->value);
        }
        $next = $c->matches($i, 'CHARACTER', 'SET') ?? $c->matches($i, 'CHARSET');
        if ($next === null) {
            return new Unknown($table, $c->text(), 'Unrecognised table option.');
        }

        return new TableOption($table, 'DEFAULT CHARSET', $c->at($c->skipEquals($next))?->value);
    }

    // ---------------------------------------------------------------- other statements

    private function create(TokenList $t, string $text): ParsedStatement
    {
        $i = 1;
        if ($t->matches($i, 'OR', 'REPLACE') !== null) {
            $i += 2;
        }
        if ($t->isWord($i, 'TEMPORARY')) {
            $i++;
        }

        if ($t->isWord($i, 'TABLE')) {
            $i++;
            if ($t->matches($i, 'IF', 'NOT', 'EXISTS') !== null) {
                $i += 3;
            }
            [$table] = $t->name($i);
            if ($table === null) {
                return $this->unknown($text, null, 'Could not read the table name.');
            }

            $definition = null;
            $parser = new Parser($text);
            $statement = $parser->statements[0] ?? null;
            if ($statement instanceof CreateStatement) {
                $definition = DefinitionReader::table($statement);
            }

            return new ParsedStatement($text, ParsedStatement::CREATE_TABLE, $table, [new CreateTable($table, $definition)]);
        }

        if ($t->isWord($i, 'UNIQUE', 'FULLTEXT', 'SPATIAL', 'INDEX')) {
            $type = match ($t->at($i)?->upper()) {
                'UNIQUE' => Index::UNIQUE,
                'FULLTEXT' => Index::FULLTEXT,
                'SPATIAL' => Index::SPATIAL,
                default => Index::INDEX,
            };
            if ($type !== Index::INDEX) {
                $i++;
            }
            if (! $t->isWord($i, 'INDEX')) {
                return $this->unknown($text, null, 'Unrecognised CREATE statement.');
            }
            [$name, $i] = $t->name($i + 1);
            if ($t->isWord($i, 'USING')) {
                $i += 2;
            }
            if ($name === null || ! $t->isWord($i, 'ON')) {
                return $this->unknown($text, null, 'Could not read CREATE INDEX.');
            }
            [$table, $i] = $t->name($i + 1);
            if ($table === null) {
                return $this->unknown($text, null, 'Could not read the table name.');
            }
            [$columns, $i] = $this->indexColumns($t, $i);
            [$algorithm, $lock] = $this->trailingAlgorithmLock($t, $i);

            return new ParsedStatement($text, ParsedStatement::CREATE_INDEX, $table, [new AddIndex($table, new Index($name, $type, $columns))], $algorithm, $lock);
        }

        if ($t->isWord($i, 'VIEW', 'TRIGGER', 'PROCEDURE', 'FUNCTION', 'EVENT', 'DEFINER', 'ALGORITHM', 'SQL')) {
            return new ParsedStatement($text, ParsedStatement::IGNORED, null, [new Ignored('CREATE '.$t->at($i)?->upper())]);
        }

        return $this->unknown($text, null, 'Unrecognised CREATE statement.');
    }

    private function drop(TokenList $t, string $text): ParsedStatement
    {
        $i = 1;
        if ($t->isWord($i, 'TEMPORARY')) {
            $i++;
        }

        if ($t->isWord($i, 'TABLE', 'TABLES')) {
            $i++;
            if ($t->matches($i, 'IF', 'EXISTS') !== null) {
                $i += 2;
            }
            $operations = [];
            foreach ($t->splitTopLevel($i) as $part) {
                [$name] = $part->name(0);
                if ($name !== null && ! $part->isWord(0, 'RESTRICT', 'CASCADE')) {
                    $operations[] = new DropTable($name);
                }
            }
            if ($operations === []) {
                return $this->unknown($text, null, 'Could not read the table name.');
            }

            return new ParsedStatement($text, ParsedStatement::DROP_TABLE, $operations[0]->table, $operations);
        }

        if ($t->isWord($i, 'INDEX')) {
            [$name, $i] = $t->name($i + 1);
            if ($name === null || ! $t->isWord($i, 'ON')) {
                return $this->unknown($text, null, 'Could not read DROP INDEX.');
            }
            [$table, $i] = $t->name($i + 1);
            if ($table === null) {
                return $this->unknown($text, null, 'Could not read the table name.');
            }
            [$algorithm, $lock] = $this->trailingAlgorithmLock($t, $i);
            $operation = strcasecmp($name, 'PRIMARY') === 0 ? new DropPrimaryKey($table) : new DropIndex($table, $name);

            return new ParsedStatement($text, ParsedStatement::DROP_INDEX, $table, [$operation], $algorithm, $lock);
        }

        if ($t->isWord($i, 'VIEW', 'TRIGGER', 'PROCEDURE', 'FUNCTION', 'EVENT')) {
            return new ParsedStatement($text, ParsedStatement::IGNORED, null, [new Ignored('DROP '.$t->at($i)?->upper())]);
        }

        return $this->unknown($text, null, 'Unrecognised DROP statement.');
    }

    private function renameTable(TokenList $t, string $text): ParsedStatement
    {
        if (! $t->isWord(1, 'TABLE', 'TABLES')) {
            return $this->unknown($text, null, 'Unrecognised RENAME statement.');
        }

        $operations = [];
        foreach ($t->splitTopLevel(2) as $pair) {
            [$from, $i] = $pair->name(0);
            [$to] = $pair->isWord($i, 'TO') ? $pair->name($i + 1) : [null, $i];
            if ($from === null || $to === null) {
                return $this->unknown($text, null, 'Could not read RENAME TABLE.');
            }
            $operations[] = new RenameTable($from, $to);
        }

        return new ParsedStatement($text, ParsedStatement::RENAME_TABLE, $operations[0]->table ?? null, $operations);
    }

    private function truncate(TokenList $t, string $text): ParsedStatement
    {
        [$table] = $t->name($t->isWord(1, 'TABLE') ? 2 : 1);
        if ($table === null) {
            return $this->unknown($text, null, 'Could not read the table name.');
        }

        return new ParsedStatement($text, ParsedStatement::TRUNCATE, $table, [new TruncateTable($table)]);
    }

    private function dml(TokenList $t, string $text, string $verb): ParsedStatement
    {
        $i = 1;
        while ($t->isWord($i, 'LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY', 'QUICK', 'IGNORE', 'INTO', 'FROM')) {
            $i++;
        }
        [$table] = $t->name($i);

        $hasWhere = $verb === 'INSERT' || $verb === 'REPLACE';
        foreach ($t->toks as $tok) {
            if ($tok->isWord('WHERE', 'LIMIT')) {
                $hasWhere = true;
            }
        }

        return new ParsedStatement($text, ParsedStatement::DML, $table, [new DataChange($table, $verb, $hasWhere)]);
    }

    private function set(string $text): ParsedStatement
    {
        $operations = [];
        $body = (string) preg_replace('/^\s*SET\s+/i', '', $text);
        foreach (preg_split('/\s*,\s*/', $body) ?: [] as $assignment) {
            if (preg_match('/^(?:(?:SESSION|LOCAL|GLOBAL|PERSIST)\s+)?@{0,2}(?:(?:session|local|global)\.)?`?(\w+)`?\s*:?=\s*(.+)$/i', trim($assignment), $m)) {
                $operations[] = new SetVariable(strtolower($m[1]), trim($m[2], " '\""));
            }
        }

        if ($operations === []) {
            return new ParsedStatement($text, ParsedStatement::IGNORED, null, [new Ignored('SET')]);
        }

        return new ParsedStatement($text, ParsedStatement::SET, null, $operations);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function trailingAlgorithmLock(TokenList $t, int $i): array
    {
        $algorithm = null;
        $lock = null;
        for ($n = $t->count(); $i < $n; $i++) {
            if ($t->isWord($i, 'ALGORITHM')) {
                $algorithm = strtoupper((string) $t->at($t->skipEquals($i + 1))?->value);
            } elseif ($t->isWord($i, 'LOCK')) {
                $lock = strtoupper((string) $t->at($t->skipEquals($i + 1))?->value);
            }
        }

        return [$algorithm, $lock];
    }

    private function unknown(string $text, ?string $table, string $reason): ParsedStatement
    {
        return new ParsedStatement($text, ParsedStatement::UNKNOWN, $table, [new Unknown($table, $text, $reason)]);
    }
}
