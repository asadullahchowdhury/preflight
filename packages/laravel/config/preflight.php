<?php

declare(strict_types=1);

return [

    /*
    | The MySQL version production runs, e.g. "8.0.36". Online-DDL behaviour changes between minor versions
    | (INSTANT ADD/DROP COLUMN arrived in 8.0.29), so set this when CI uses a different server than production.
    | When empty, the version recorded in the stats file is used, then the version of the connected server.
    */
    'mysql_version' => env('PREFLIGHT_MYSQL_VERSION'),

    /*
    | Database connection preflight:check analyses. Null means the default connection.
    */
    'connection' => env('PREFLIGHT_CONNECTION'),

    /*
    | Production table sizes written by `php artisan preflight:stats`. With them, findings get duration
    | estimates and severity reflects how long writes would actually be blocked.
    */
    'stats_path' => env('PREFLIGHT_STATS_PATH'),

    /*
    | Exit with a failure code when the report reaches this severity: "danger", "warn" or "never".
    */
    'fail_on' => env('PREFLIGHT_FAIL_ON', 'danger'),

];
