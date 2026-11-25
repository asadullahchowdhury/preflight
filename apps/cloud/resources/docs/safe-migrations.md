# Writing safe migrations

Every risky finding comes with a safer alternative. These are the patterns behind them.

## Change a column's type: expand and contract

Changing a type, or widening a `VARCHAR` past 255 bytes, copies the table. Do it in steps instead:

1. Add the new column, nullable (instant):
    ```php
    $table->string('status_new', 100)->nullable();
    ```
2. Make the app write both columns, and backfill old rows in batches from a queued job:
    ```php
    DB::table('orders')->whereNull('status_new')->chunkById(1000, function ($rows) {
        // copy status into status_new for these rows
    });
    ```
3. Switch reads to the new column.
4. In a later deploy, drop the old column and rename the new one (both instant on MySQL 8.0.29+).

For a one-off change on a huge table, `gh-ost` or `pt-online-schema-change` copy the table in the background without blocking writes.

## Add a foreign key without copying the table

With foreign key checks on, MySQL copies the table to validate every row. With them off, it only records the constraint:

```php
Schema::withoutForeignKeyConstraints(function () {
    Schema::table('orders', function (Blueprint $table) {
        $table->foreign('user_id')->references('id')->on('users');
    });
});
```

Existing rows are then not checked, so look for orphans first:

```sql
SELECT COUNT(*) FROM orders c
LEFT JOIN users p ON p.id = c.user_id
WHERE c.user_id IS NOT NULL AND p.id IS NULL;
```

## Keep online changes online

Index builds and in-place rebuilds let writes continue. Pin the algorithm so MySQL refuses, rather than silently locking, if that ever changes:

```php
DB::statement('ALTER TABLE orders ADD INDEX orders_customer_email_index (customer_email), ALGORITHM=INPLACE, LOCK=NONE');
```

## Don't let a migration stall the app

Every `ALTER`, even an instant one, needs a short exclusive metadata lock. It waits behind any open transaction on the table, and every new query queues behind it. Give up quickly instead:

```php
public function up(): void
{
    DB::statement('SET SESSION lock_wait_timeout = 5');
    // …
}
```

If it times out, retry the deploy.

## Drop and rename columns in two deploys

Servers still running the old code keep using the old column until the deploy finishes. First ship code that no longer uses the column (remove it from `$fillable`, `$casts`, queries and API resources), then the migration that drops or renames it.

## Backfill in batches

An `UPDATE` without `WHERE` locks every row in one transaction and makes replicas lag. Move large data changes into a queued job that works in batches with `chunkById`.
