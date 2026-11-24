<?php

declare(strict_types=1);

use Preflight\Core\Report\Algorithm;
use Preflight\Core\Report\Classification;
use Preflight\Core\Report\LockLevel;
use Preflight\Core\Report\StatementResult;
use Preflight\Core\Sql\StatementParser;
use Preflight\Core\Sql\TokenList;
use Preflight\Core\Tests\Support\Fixture;

/*
 * Ground truth from a real MySQL server.
 *
 * For every fixture, the engine predicts how each online-DDL statement runs on *this* server's version.
 * The prediction is then checked by MySQL itself:
 *   1. the predicted ALGORITHM/LOCK must be accepted;
 *   2. the next stricter one (INSTANT < INPLACE < COPY, NONE < SHARED) must be refused (error 1845/1846);
 *   3. the table must be rebuilt (new InnoDB table id) exactly when the engine says so.
 *
 *   PREFLIGHT_ORACLE_DSN="mysql:host=127.0.0.1;dbname=preflight_oracle" vendor/bin/pest --group=oracle
 *
 * The database name must contain "preflight": every table in it is dropped between cases.
 */

function oracle(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = (string) getenv('PREFLIGHT_ORACLE_DSN');
    if (! preg_match('/dbname=([^;]*preflight[^;]*)/i', $dsn)) {
        throw new RuntimeException('PREFLIGHT_ORACLE_DSN must name a scratch database containing "preflight".');
    }

    $pdo = new PDO($dsn, getenv('PREFLIGHT_ORACLE_USER') ?: 'root', getenv('PREFLIGHT_ORACLE_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    return $pdo;
}

function oracleReset(PDO $pdo): void
{
    $pdo->exec('SET SESSION foreign_key_checks = 0');
    foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')?->fetchAll(PDO::FETCH_NUM) ?? [] as [$table]) {
        $pdo->exec('DROP TABLE `'.str_replace('`', '``', (string) $table).'`');
    }
    $pdo->exec('SET SESSION foreign_key_checks = 1');
}

function oracleTableId(PDO $pdo, ?string $table): ?string
{
    if ($table === null) {
        return null;
    }
    $statement = $pdo->prepare('SELECT TABLE_ID FROM information_schema.INNODB_TABLES WHERE LOWER(NAME) = LOWER(CONCAT(DATABASE(), "/", ?))');
    $statement->execute([$table]);
    $id = $statement->fetchColumn();

    return $id === false ? null : (string) $id;
}

function withAlgorithm(string $sql, string $kind, Algorithm $algorithm, ?LockLevel $lock): string
{
    $sql = rtrim(trim($sql), ';');
    $clauses = ['ALGORITHM='.$algorithm->value];
    if ($lock !== null) {
        $clauses[] = 'LOCK='.$lock->value;
    }

    return $kind === 'alter' ? $sql.', '.implode(', ', $clauses) : $sql.' '.implode(' ', $clauses);
}

/**
 * @return list<string> problems found
 */
function verifyStatement(PDO $pdo, StatementResult $result, string $kind, Classification $predicted): array
{
    $problems = [];
    $label = preg_replace('/\s+/', ' ', $result->sql);

    // 2. A stricter algorithm / lock must be refused.
    $stricter = [];
    if ($predicted->algorithm === Algorithm::Inplace) {
        $stricter[] = [Algorithm::Instant, null];
    } elseif ($predicted->algorithm === Algorithm::Copy) {
        $stricter[] = [Algorithm::Inplace, LockLevel::Exclusive];
    }
    if ($predicted->lock !== LockLevel::None && $predicted->algorithm !== Algorithm::Instant) {
        $stricter[] = [$predicted->algorithm, LockLevel::None];
    }

    foreach ($stricter as [$algorithm, $lock]) {
        try {
            $pdo->exec(withAlgorithm($result->sql, $kind, $algorithm, $lock));
            $problems[] = "{$label}: MySQL accepted ALGORITHM={$algorithm->value}".($lock ? " LOCK={$lock->value}" : '')
                ." but Preflight predicted {$predicted->algorithm->value}/{$predicted->lock->value}.";

            return $problems; // The change is applied now; later checks would be meaningless.
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (! in_array($code, [1845, 1846], true)) {
                $problems[] = "{$label}: stricter attempt failed for an unrelated reason ({$code}: {$e->getMessage()}).";
            }
        }
    }

    // 1. The predicted algorithm / lock must be accepted. 3. Rebuild must match.
    $before = oracleTableId($pdo, $result->table);
    try {
        $lock = $predicted->algorithm === Algorithm::Instant ? null : $predicted->lock;
        $pdo->exec(withAlgorithm($result->sql, $kind, $predicted->algorithm, $lock));
    } catch (PDOException $e) {
        $problems[] = "{$label}: MySQL refused the predicted ALGORITHM={$predicted->algorithm->value} LOCK={$predicted->lock->value}: {$e->getMessage()}";

        return $problems;
    }
    $after = oracleTableId($pdo, $result->table);

    if ($before !== null && $after !== null) {
        $rebuilt = $before !== $after;
        if ($rebuilt !== $predicted->rebuildsTable()) {
            $problems[] = "{$label}: table ".($rebuilt ? 'was' : 'was not').' rebuilt, but Preflight predicted it '.($predicted->rebuildsTable() ? 'would be' : 'would not be').'.';
        }
    }

    return $problems;
}

$oracleCases = array_filter(Fixture::all(), static fn (array $case) => $case[0]->runsOnOracle());

it('agrees with a real MySQL server', function (Fixture $fixture) {
    if (getenv('PREFLIGHT_ORACLE_DSN') === false) {
        $this->markTestSkipped('Set PREFLIGHT_ORACLE_DSN to run oracle tests.');
    }

    $pdo = oracle();
    $version = (string) $pdo->query('SELECT VERSION()')?->fetchColumn();
    oracleReset($pdo);

    foreach (TokenList::statements($fixture->schemaSql()) as $statement) {
        $pdo->exec($statement->text());
    }
    $pdo->exec('SET SESSION foreign_key_checks = '.($fixture->foreignKeyChecks() ? 1 : 0));

    $report = $fixture->analyze($version);
    $results = [];
    foreach ($report->statements as $result) {
        $results[$result->index] = $result;
    }

    $parser = new StatementParser;
    $problems = [];
    foreach (TokenList::statements($fixture->sql()) as $index => $tokens) {
        $parsed = $parser->statement($tokens);
        $result = $results[$index] ?? null;

        if ($result !== null && $result->effective !== null && $parsed->isOnlineDdl()) {
            $kind = $parsed->kind === 'alter' ? 'alter' : 'index';
            array_push($problems, ...verifyStatement($pdo, $result, $kind, $result->effective));
        } else {
            $pdo->exec($tokens->text());
        }
    }

    $pdo->exec('SET SESSION foreign_key_checks = 1');

    expect($problems)->toBe([], "MySQL {$version} disagrees:\n".implode("\n", $problems));
})->with($oracleCases)->group('oracle');
