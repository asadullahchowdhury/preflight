<?php

declare(strict_types=1);

namespace Preflight\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Preflight\Core\Render\MarkdownRenderer;
use Preflight\Core\Render\TextRenderer;
use Preflight\Core\Report\Severity;
use Preflight\Laravel\Check\Checker;
use Preflight\Laravel\Check\CheckResult;
use Preflight\Laravel\Migrations\PreflightMigrator;
use Preflight\Laravel\PreflightException;
use Preflight\Laravel\Stats\StatsFile;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends Command
{
    protected $signature = 'preflight:check
        {--base= : Git ref of the base branch (e.g. origin/main). Migrations added since then are checked; older pending migrations are run first}
        {--migration=* : Check only these migrations (name or path); nothing is run}
        {--database= : The database connection to use}
        {--path=* : The path(s) to the migration files}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--mysql= : MySQL version production runs, e.g. 8.0.36 (default: config, then the stats file, then the connected server)}
        {--stats= : Production table sizes written by preflight:stats}
        {--format=text : Output format: text, markdown or json}
        {--markdown= : Also write a markdown report (for a PR comment) to this file}
        {--json= : Also write a JSON report to this file}
        {--fail-on= : Exit with code 1 at this severity: danger, warn or never (default: config, "danger")}
        {--force : Allow --base to run migrations when the app environment is production}';

    protected $description = 'Check pending migrations for MySQL schema changes that lock or copy tables';

    public function handle(DatabaseManager $db): int
    {
        try {
            // By name: Laravel 11 does not bind the Migrator class itself.
            $appMigrator = $this->laravel->make('migrator');
            if (! $appMigrator instanceof Migrator) {
                throw new PreflightException('The [migrator] binding is not a Laravel migrator.');
            }

            $format = $this->choiceOption('format', ['text', 'markdown', 'json'], 'text');
            $failOn = $this->choiceOption('fail-on', ['danger', 'warn', 'never'], $this->configString('preflight.fail_on') ?? 'danger');

            $connectionName = $this->stringOption('database') ?? $this->configString('preflight.connection');
            $connection = $db->connection($connectionName);
            if (! $connection instanceof Connection) {
                throw new PreflightException('Unsupported database connection.');
            }

            $migrator = new PreflightMigrator($this->laravel->make('migration.repository'), $db, $this->laravel->make('files'), $this->laravel->make('events'));
            $paths = $this->migrationPaths($appMigrator);
            $statsPath = $this->stringOption('stats') ?? $this->configString('preflight.stats_path');
            $stats = $statsPath !== null ? StatsFile::read($statsPath) : null;
            $quiet = $format !== 'text';

            $checker = (new Checker($migrator))->onProgress(function (string $message) use ($quiet): void {
                if (! $quiet) {
                    $this->components->info($message);
                }
            });

            $result = $migrator->usingConnection($connection->getName(), fn () => $checker->check(
                connection: $connection,
                paths: $paths,
                base: $this->stringOption('base'),
                only: $this->listOption('migration'),
                mysqlVersion: $this->stringOption('mysql') ?? $this->configString('preflight.mysql_version'),
                stats: $stats,
                confirmBaseRun: fn (array $names) => $this->confirmBaseRun($connection),
            ));
        } catch (PreflightException|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        /** @var CheckResult $result */
        $this->writeFiles($result);
        $this->output->write($this->render($result, $format), false, OutputInterface::OUTPUT_RAW);

        return $this->fails($result, $failOn) ? self::FAILURE : self::SUCCESS;
    }

    private function confirmBaseRun(Connection $connection): bool
    {
        if (! $this->laravel->environment('production') || $this->option('force')) {
            return true;
        }

        throw new PreflightException("--base runs the base branch's pending migrations on [{$connection->getName()}], and the application is in production. Use a throwaway database (as in CI), or pass --force.");
    }

    private function render(CheckResult $result, string $format): string
    {
        return match ($format) {
            'json' => json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
            'markdown' => $this->markdown($result),
            default => $this->text($result),
        };
    }

    private function text(CheckResult $result): string
    {
        $out = '';
        if ($result->migrations === []) {
            $out .= "No new migrations to check.\n";
        }
        foreach ($result->notes as $note) {
            $out .= ($this->output->isDecorated() ? "\033[33mNote:\033[0m " : 'Note: ').$note."\n";
        }
        if ($result->migrations === []) {
            return $out;
        }

        return ($out !== '' ? $out."\n" : '').(new TextRenderer($this->output->isDecorated()))->render($result->report, $result->sources());
    }

    private function markdown(CheckResult $result): string
    {
        $out = (new MarkdownRenderer)->render($result->report, $result->sources());
        if ($result->migrations !== []) {
            $count = count($result->migrations);
            $out .= "\n<sub>Checked {$count} ".($count === 1 ? 'migration' : 'migrations').': '.implode(', ', array_map(static fn (string $m) => '`'.$m.'`', $result->migrations))."</sub>\n";
        }
        foreach ($result->notes as $note) {
            $out .= "\n> [!NOTE]\n> ".$note."\n";
        }

        return $out;
    }

    private function writeFiles(CheckResult $result): void
    {
        foreach (['markdown', 'json'] as $format) {
            $path = $this->stringOption($format);
            if ($path === null) {
                continue;
            }
            if (file_put_contents($path, $this->render($result, $format)) === false) {
                $this->components->warn("Could not write the {$format} report to [{$path}].");
            }
        }
    }

    private function fails(CheckResult $result, string $failOn): bool
    {
        $severity = $result->report->severity();

        return match ($failOn) {
            'danger' => $severity === Severity::Danger,
            'warn' => $severity !== Severity::Info,
            default => false,
        };
    }

    /**
     * Same resolution as `php artisan migrate`.
     *
     * @return list<string>
     */
    private function migrationPaths(Migrator $migrator): array
    {
        $paths = $this->listOption('path');
        if ($paths !== []) {
            return array_map(fn (string $path) => $this->option('realpath') ? $path : $this->laravel->basePath($path), $paths);
        }

        $paths = [];
        foreach ([...$migrator->paths(), $this->laravel->databasePath('migrations')] as $path) {
            if (is_string($path)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $choices
     */
    private function choiceOption(string $name, array $choices, string $default): string
    {
        $value = $this->stringOption($name) ?? $default;
        if (! in_array($value, $choices, true)) {
            throw new PreflightException("--{$name} must be one of: ".implode(', ', $choices).'.');
        }

        return $value;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    private function listOption(string $name): array
    {
        $value = $this->option($name);

        return is_array($value) ? array_values(array_filter($value, static fn ($v) => is_string($v) && $v !== '')) : [];
    }

    private function configString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
