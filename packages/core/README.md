# preflight/core

Deterministic MySQL online-DDL risk analysis for schema migrations. Give it the SQL a migration will run (for Laravel, the output of `php artisan migrate --pretend`), the current schema, and optionally production table sizes. It tells you, per statement:

- which **algorithm** MySQL will use (`INSTANT` / `INPLACE` / `COPY`),
- which **lock** it needs (`NONE`, or `SHARED`, which **blocks writes**),
- whether it **rebuilds the table**,
- a **duration range** when table sizes are known,
- and a **safer alternative** when it's risky.

No AI and no database access: the verdicts come from rules that the oracle test suite checks against a real MySQL server.

## Quick look

```bash
composer install
php bin/preflight-analyze --mysql=8.0.36 \
  --schema=examples/orders/schema.sql \
  --stats=examples/orders/stats.json \
  examples/orders/migration.sql
```

```
DANGER alter table `orders` modify `status` varchar(100) not null default 'pending'
        → ALGORITHM=COPY LOCK=SHARED · rebuilds table · BLOCKS WRITES · 8 min – 42 min
        [modify-column] … varchar(50) → varchar(100) (crosses the 255-byte length boundary) …
          ↳ Add a new column, backfill it in batches …, or run it with gh-ost / pt-online-schema-change.

WARN   alter table `orders` add index `orders_customer_email_index`(`customer_email`)
        → ALGORITHM=INPLACE LOCK=NONE · 3 min – 10 min

DANGER alter table `orders` add constraint `orders_user_id_foreign` foreign key …
        → ALGORITHM=COPY LOCK=SHARED · rebuilds table · BLOCKS WRITES · 8 min – 42 min
          ↳ Wrap the change in Schema::disableForeignKeyConstraints() …
```

The exit code is `1` when anything is `danger`. Add `--json` for machine-readable output, or `--markdown` for a PR comment. The renderers are `Preflight\Core\Render\TextRenderer` and `MarkdownRenderer`.

## PHP API

```php
use Preflight\Core\Analyzer;
use Preflight\Core\Schema\SchemaSnapshot;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Stats\StatsSet;

$report = (new Analyzer)->analyze(
    sql: $pretendSql,                                  // string or list of statements
    server: ServerProfile::mysql('8.0.36'),
    schema: SchemaSnapshot::fromSql($showCreateTables), // optional but strongly recommended
    stats: StatsSet::fromArray([                         // optional: enables estimates + size-aware severity
        'orders' => ['rows' => 41_200_000, 'data_bytes' => 19_756_849_152, 'index_bytes' => 6_549_825_536],
    ]),
);

$report->severity();   // Severity::Info | Warn | Danger
$report->toArray();    // statements → findings + advisories
```

The schema snapshot matters. A `MODIFY` from Laravel's `->change()` restates the whole column, and only a comparison with the current definition shows whether it's a default change (instant) or a type change (copies the table).

## Severity

| Mode | danger | warn | info |
|---|---|---|---|
| Static (no sizes) | blocks writes (COPY, `LOCK=SHARED`) | rebuild, index build, destructive changes | instant / metadata only |
| Size-aware | writes blocked ≥ 10s | writes blocked ≥ 1s, or online work ≥ 5 min | everything else |

Operations on a table created earlier in the same batch are always `info`, because the table is empty.

## Tests

```bash
vendor/bin/pest                      # fixtures + unit tests
vendor/bin/phpstan analyse           # level max
vendor/bin/pint --test

# Oracle: checks the engine against a real MySQL. The database name must contain "preflight";
# every table in it is dropped between cases.
PREFLIGHT_ORACLE_DSN="mysql:host=127.0.0.1;dbname=preflight_oracle" vendor/bin/pest --group=oracle
../../scripts/oracle-matrix.sh 8.0.28 8.4   # the same against several MySQL versions in Docker
# Windows without Docker: every mysql-8.* build in Laragon's bin/mysql, as a temporary server on port 3307
powershell -File ../../scripts/oracle-matrix.ps1 -MysqlRoot D:/laragon/bin/mysql
```

**Fixtures** (`tests/Fixtures/mysql/*.yaml`) are the source of truth for the rules. Each case:

```yaml
- name: widening varchar across 255 bytes copies the table
  server: 8.0.30            # optional, default 8.0.30
  schema: orders            # a name from tests/Fixtures/schemas.yaml, or inline CREATE TABLE SQL
  vars: { foreign_key_checks: 0 }   # optional session vars
  stats: { orders: { rows: 1000, data_bytes: 1048576, index_bytes: 0 } }  # optional
  sql: alter table `orders` modify `status` varchar(100) not null default 'new'
  expect:                   # every finding, in order; only listed keys are compared
    - { rule: modify-column, algorithm: COPY, lock: SHARED, rebuild: true, severity: danger }
  statement: { algorithm: COPY, lock: SHARED, rebuild: true }   # optional: whole-statement result
  advisories: [mdl-wait]    # optional: exact set of advisory ids
  oracle: false             # optional: skip in the oracle suite (DML, unknown statements…)
```

**Oracle tests** re-analyse every fixture for the connected server's version, then ask MySQL directly:

1. The predicted `ALGORITHM=…, LOCK=…` must be accepted.
2. The next stricter one must be refused with error 1845/1846.
3. The InnoDB table id must change exactly when a rebuild is predicted.

## Rule ids

`add-column`, `drop-column`, `rename-column`, `modify-column`, `alter-column`, `add-index`, `add-unique-index`, `add-fulltext-index`, `add-spatial-index`, `add-primary-key`, `drop-primary-key`, `drop-index`, `rename-index`, `index-visibility`, `add-foreign-key`, `drop-foreign-key`, `add-check`, `table-engine`, `table-option`, `convert-charset`, `rename-table`, `create-table`, `drop-table`, `truncate-table`, `data-change`, `unknown-statement`.

Advisories: `mdl-wait`, `multiple-rebuilds`, `combined-rebuild`, `explicit-algorithm-fails`, `explicit-lock-fails`, `table-not-in-snapshot`.

## Supported

MySQL 8.0.x and 8.4 LTS, InnoDB. MySQL 5.7 (end of life), MariaDB and Postgres are not supported yet.
