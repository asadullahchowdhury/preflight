# Production table sizes

Without sizes, Preflight judges a change by what MySQL does: a table copy is always **danger**. With your production table sizes, it estimates how long each change takes. The same copy is then **danger** on a 40-million-row table and **safe** on a 12-row one.

Only table names, row estimates and byte counts are sent, from `information_schema`. No row data is ever read.

## With Preflight Cloud

1. On your repository's page in Preflight, open **Table sizes** and click **Create token**.
2. Add the two values it shows to production's environment:

    ```dotenv
    PREFLIGHT_CLOUD_URL=https://your-preflight-host
    PREFLIGHT_STATS_TOKEN=pfs_…
    ```

3. Schedule the push, for example in `routes/console.php`:

    ```php
    Schedule::command('preflight:stats --push')->daily();
    ```

Every check after that uses the latest sizes and the MySQL version production reports. The dashboard keeps 90 days of history and shows how each table grows. If the sizes get more than two weeks old, checks say so.

## Without Preflight Cloud

Write the sizes to a file in production and pass it to the check:

```bash
php artisan preflight:stats --output=preflight-stats.json
php artisan preflight:check --stats=preflight-stats.json
```

In the GitHub Action, use the `stats-file` input.

## How estimates are made

Estimates use throughput ranges per kind of work, applied to the table's size:

| Work                     | Throughput used                  |
| ------------------------ | -------------------------------- |
| Copying a table (`COPY`) | 10–50 MB/s over data and indexes |
| Rebuilding in place      | 20–80 MB/s over data and indexes |
| Building an index        | 30–120 MB/s over the data        |
| Metadata only            | under a second                   |

They are always ranges: row counts in `information_schema` are estimates, and real speed depends on your hardware and load.
