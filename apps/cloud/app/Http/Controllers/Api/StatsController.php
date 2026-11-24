<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Repository;
use App\Models\TableStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/stats: production table sizes from `php artisan preflight:stats --push`, authenticated with the
 * repository's stats token. Numbers only: table names, row estimates and byte sizes.
 */
class StatsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $repository = $token !== null ? Repository::forStatsToken($token) : null;
        if ($repository === null) {
            return response()->json(['message' => 'Invalid stats token. Create one on the repository page in Preflight.'], 401);
        }

        $data = $request->validate([
            'preflight_stats' => ['required', 'integer', 'in:1'],
            'server_version' => ['nullable', 'string', 'max:40'],
            'captured_at' => ['nullable', 'date'],
            'tables' => ['present', 'array', 'max:5000'],
            'tables.*.rows' => ['required', 'integer', 'min:0'],
            'tables.*.data_bytes' => ['required', 'integer', 'min:0'],
            'tables.*.index_bytes' => ['required', 'integer', 'min:0'],
        ]);

        $tables = (array) $data['tables'];
        foreach (array_keys($tables) as $name) {
            if (! is_string($name) || $name === '' || strlen($name) > 255) {
                return response()->json(['message' => 'Table names must be non-empty strings.'], 422);
            }
        }

        $day = Carbon::parse($data['captured_at'] ?? now())->toDateString();

        DB::transaction(function () use ($repository, $tables, $day, $data): void {
            $now = now();
            $rows = [];
            foreach ($tables as $name => $table) {
                $rows[] = [
                    'repository_id' => $repository->id,
                    'table_name' => (string) $name,
                    'captured_on' => $day,
                    'rows' => (int) $table['rows'],
                    'data_bytes' => (int) $table['data_bytes'],
                    'index_bytes' => (int) $table['index_bytes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // A second push on the same day replaces that day's snapshot.
            TableStat::query()->where('repository_id', $repository->id)->whereDate('captured_on', $day)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                TableStat::query()->insert($chunk);
            }

            // Keep 90 days of history.
            TableStat::query()->where('repository_id', $repository->id)->whereDate('captured_on', '<', now()->subDays(90)->toDateString())->delete();

            $repository->update([
                'stats_pushed_at' => $now,
                'stats_captured_on' => $day,
                'stats_server_version' => $data['server_version'] ?? $repository->stats_server_version,
            ]);
        });

        return response()->json([
            'message' => 'Stored sizes of '.count($tables).' tables for '.$repository->full_name.'.',
            'tables' => count($tables),
        ], 201);
    }
}
