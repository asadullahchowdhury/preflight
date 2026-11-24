<?php

declare(strict_types=1);

namespace Preflight\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Preflight\Laravel\Database\MySqlInspector;
use Preflight\Laravel\PreflightException;
use Preflight\Laravel\Stats\StatsFile;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Captures table sizes (row estimates, data and index bytes) from information_schema. Never reads row data.
 */
final class StatsCommand extends Command
{
    protected $signature = 'preflight:stats
        {--database= : The database connection to use}
        {--output= : Write the JSON to this file instead of stdout}';

    protected $description = 'Capture table sizes so preflight:check can estimate how long schema changes take';

    public function handle(DatabaseManager $db): int
    {
        try {
            $name = $this->option('database');
            $connection = $db->connection(is_string($name) && $name !== '' ? $name : (is_string(config('preflight.connection')) ? config('preflight.connection') : null));
            if (! $connection instanceof Connection) {
                throw new PreflightException('Unsupported database connection.');
            }

            $inspector = new MySqlInspector($connection);
            $stats = new StatsFile($inspector->tableStats(), $inspector->version(), now()->toIso8601String());
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
}
