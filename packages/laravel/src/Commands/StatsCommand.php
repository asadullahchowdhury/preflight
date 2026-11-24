<?php

declare(strict_types=1);

namespace Preflight\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\Factory as Http;
use Preflight\Laravel\Database\MySqlInspector;
use Preflight\Laravel\PreflightException;
use Preflight\Laravel\Stats\StatsFile;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Captures table sizes (row estimates, data and index bytes) from information_schema. Never reads row data.
 *
 * With --push, sends them to Preflight Cloud so checks on pull requests use production sizes. Schedule it:
 *
 *   Schedule::command('preflight:stats --push')->daily();
 */
final class StatsCommand extends Command
{
    protected $signature = 'preflight:stats
        {--database= : The database connection to use}
        {--output= : Write the JSON to this file instead of stdout}
        {--push : Send the sizes to Preflight Cloud (PREFLIGHT_CLOUD_URL and PREFLIGHT_STATS_TOKEN)}';

    protected $description = 'Capture table sizes so preflight:check can estimate how long schema changes take';

    public function handle(DatabaseManager $db, Http $http): int
    {
        try {
            $name = $this->option('database');
            $connection = $db->connection(is_string($name) && $name !== '' ? $name : (is_string(config('preflight.connection')) ? config('preflight.connection') : null));
            if (! $connection instanceof Connection) {
                throw new PreflightException('Unsupported database connection.');
            }

            $inspector = new MySqlInspector($connection);
            $stats = new StatsFile($inspector->tableStats(), $inspector->version(), now()->toIso8601String());

            if ($this->option('push')) {
                return $this->push($http, $stats);
            }
        } catch (PreflightException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        $json = json_encode($stats->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            if (file_put_contents($output, $json) === false) {
                $this->components->error("Could not write [{$output}].");

                return self::FAILURE;
            }
            $this->components->info('Wrote sizes of '.count($stats->tables)." tables to [{$output}].");

            return self::SUCCESS;
        }

        $this->output->write($json, false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function push(Http $http, StatsFile $stats): int
    {
        $url = config('preflight.cloud_url');
        $token = config('preflight.stats_token');
        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            throw new PreflightException('Set PREFLIGHT_CLOUD_URL and PREFLIGHT_STATS_TOKEN (create the token on the repository page in Preflight).');
        }

        try {
            $response = $http->acceptJson()
                ->withToken($token)
                ->timeout(20)
                ->retry(2, 1000, throw: false)
                ->post(rtrim($url, '/').'/api/stats', $stats->toArray());
        } catch (Throwable $e) {
            $this->components->error('Could not reach Preflight Cloud: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $message = $response->json('message');
            $this->components->error('Preflight Cloud answered '.$response->status().(is_string($message) ? ': '.$message : '.'));

            return self::FAILURE;
        }

        $this->components->info('Sent sizes of '.count($stats->tables).' tables to Preflight Cloud.');

        return self::SUCCESS;
    }
}
