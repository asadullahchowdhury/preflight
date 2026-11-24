<?php

declare(strict_types=1);

namespace Preflight\Laravel\Check;

use Closure;
use Illuminate\Database\Connection;
use Preflight\Core\Analyzer;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Sql\TokenList;
use Preflight\Laravel\Database\MySqlInspector;
use Preflight\Laravel\Git\MigrationChanges;
use Preflight\Laravel\Migrations\PreflightMigrator;
use Preflight\Laravel\PreflightException;
use Preflight\Laravel\Stats\StatsFile;

/**
 * Works out which migrations are new, builds the schema they run against, captures their SQL and analyses it.
 *
 *  - Default: every pending migration is new; the database as it is now is the "before" schema. Read-only.
 *  - With a git base ref: migrations the branch adds are new; other pending migrations are run first, so a
 *    fresh CI database ends up in the base branch's state. This writes to the database.
 *  - With explicit files: those migrations are new; nothing is run.
 */
final class Checker
{
    /** @var Closure(string): void */
    private Closure $progress;

    public function __construct(
        private readonly PreflightMigrator $migrator,
        private readonly Analyzer $analyzer = new Analyzer,
    ) {
        $this->progress = static function (string $message): void {};
    }

    /**
     * @param  Closure(string): void  $progress
     */
    public function onProgress(Closure $progress): self
    {
        $this->progress = $progress;

        return $this;
    }

    /**
     * @param  list<string>  $paths  absolute migration directories or files
     * @param  list<string>  $only  migration names or file paths to treat as new
     * @param  ?Closure(list<string>): bool  $confirmBaseRun  asked before migrations are run; return false to abort
     */
    public function check(
        Connection $connection,
        array $paths,
        ?string $base = null,
        array $only = [],
        ?string $mysqlVersion = null,
        ?StatsFile $stats = null,
        ?Closure $confirmBaseRun = null,
    ): CheckResult {
        $inspector = new MySqlInspector($connection);
        $connectedVersion = $inspector->version();

        $files = $this->migrator->getMigrationFiles($paths);
        $ran = $this->ran();
        $pending = array_filter($files, static fn (string $path, string $name) => ! isset($ran[$name]), ARRAY_FILTER_USE_BOTH);

        $notes = [];
        $targets = $this->targets($files, $pending, $ran, $base, $only, $paths, $notes);

        // Build the "before" schema: run pending migrations that are not under review.
        $baseRun = array_values(array_diff_key($pending, $targets));
        $baseMigrations = [];
        if ($base !== null && $baseRun !== []) {
            $names = array_map(fn (string $path) => $this->migrator->getMigrationName($path), $baseRun);
            if ($confirmBaseRun !== null && ! $confirmBaseRun($names)) {
                throw new PreflightException('Aborted before running the base migrations.');
            }
            ($this->progress)('Migrating the base schema ('.count($baseRun).' '.(count($baseRun) === 1 ? 'migration' : 'migrations').')…');
            $this->ensureRepository();
            $this->migrator->runPending($baseRun);
            $baseMigrations = $names;
        }

        ($this->progress)('Reading the schema…');
        $snapshot = $inspector->snapshot();

        $statements = [];
        $labels = [];
        $analysed = [];
        foreach ($targets as $name => $path) {
            $connectionName = $this->migrator->connectionFor($path);
            if ($connectionName !== null && $connectionName !== $connection->getName()) {
                $notes[] = "{$name} runs on the [{$connectionName}] connection, not [{$connection->getName()}]; it was not analysed.";

                continue;
            }
            if (! $this->migrator->shouldRun($path)) {
                $notes[] = "{$name} was skipped: its shouldRun() returned false.";

                continue;
            }

            $analysed[] = $name;
            foreach ($this->migrator->pretend($path) as $sql) {
                $count = count(TokenList::statements($sql));
                for ($i = 0; $i < $count; $i++) {
                    $labels[count($labels)] = $name;
                }
                $statements[] = $sql;
            }
        }

        $server = ServerProfile::mysql(
            $mysqlVersion ?? $stats->serverVersion ?? $connectedVersion,
            $inspector->foreignKeyChecks(),
            $inspector->defaultCharset(),
        );

        $report = $this->analyzer->analyze($statements, $server, $snapshot, $stats?->toStatsSet());

        return new CheckResult($report, $analysed, $labels, $notes, $baseMigrations, $connectedVersion);
    }

    /**
     * @param  array<string, string>  $files  name => path
     * @param  array<string, string>  $pending
     * @param  array<string, true>  $ran
     * @param  list<string>  $only
     * @param  list<string>  $paths
     * @param  list<string>  $notes
     * @return array<string, string> name => path, in run order
     */
    private function targets(array $files, array $pending, array $ran, ?string $base, array $only, array $paths, array &$notes): array
    {
        if ($only !== []) {
            $selected = [];
            foreach ($only as $wanted) {
                $name = $this->migrator->getMigrationName($wanted);
                if (! isset($files[$name])) {
                    throw new PreflightException("Migration [{$wanted}] was not found in the migration paths.");
                }
                $selected[$name] = $files[$name];
            }

            return $this->withoutRan($selected, $ran, $notes);
        }

        if ($base === null) {
            return $pending;
        }

        $directories = array_values(array_unique(array_map(static fn (string $path) => is_dir($path) ? $path : dirname($path), $paths)));
        $changes = MigrationChanges::since($base, $directories);

        $selected = [];
        foreach ($files as $name => $path) {
            if (MigrationChanges::contains($changes->added, $path)) {
                $selected[$name] = $path;
            } elseif (MigrationChanges::contains($changes->modified, $path)) {
                $notes[] = "{$name} already exists on the base branch and was edited. Preflight only analyses new migrations: editing a migration that has already run in production changes nothing there.";
            }
        }

        return $this->withoutRan($selected, $ran, $notes);
    }

    /**
     * @param  array<string, string>  $selected
     * @param  array<string, true>  $ran
     * @param  list<string>  $notes
     * @return array<string, string>
     */
    private function withoutRan(array $selected, array $ran, array &$notes): array
    {
        foreach (array_keys($selected) as $name) {
            if (isset($ran[$name])) {
                $notes[] = "{$name} has already run on this database, so the schema it starts from is gone and it was not analysed. Roll it back, or run preflight:check on a fresh database.";
                unset($selected[$name]);
            }
        }
        ksort($selected);

        return $selected;
    }

    /**
     * @return array<string, true>
     */
    private function ran(): array
    {
        $repository = $this->migrator->getRepository();
        if (! $repository->repositoryExists()) {
            return [];
        }

        return array_fill_keys($repository->getRan(), true);
    }

    private function ensureRepository(): void
    {
        $repository = $this->migrator->getRepository();
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
    }
}
