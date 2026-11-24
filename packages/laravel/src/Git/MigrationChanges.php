<?php

declare(strict_types=1);

namespace Preflight\Laravel\Git;

use Preflight\Laravel\PreflightException;
use Symfony\Component\Process\Process;

/**
 * Which migration files a branch adds or edits compared with its base, according to git.
 *
 * Compares the working tree with the merge base of `$base` and HEAD, so committed, uncommitted and
 * untracked files all count. In CI, check out with enough history for the merge base (fetch-depth: 0).
 */
final class MigrationChanges
{
    /**
     * @param  list<string>  $added  absolute paths
     * @param  list<string>  $modified  absolute paths
     */
    public function __construct(
        public readonly array $added,
        public readonly array $modified,
    ) {}

    /**
     * @param  list<string>  $directories  absolute migration directories
     */
    public static function since(string $base, array $directories): self
    {
        $directories = array_values(array_filter($directories, 'is_dir'));
        if ($directories === []) {
            return new self([], []);
        }

        $cwd = $directories[0];
        $root = self::git(['rev-parse', '--show-toplevel'], $cwd);
        self::git(['rev-parse', '--verify', '--quiet', $base.'^{commit}'], $cwd,
            "Git ref [{$base}] was not found. In CI, fetch the base branch (actions/checkout with fetch-depth: 0).");
        $mergeBase = self::git(['merge-base', $base, 'HEAD'], $cwd,
            "No common history between [{$base}] and HEAD. In CI, fetch full history (actions/checkout with fetch-depth: 0).");

        $relative = array_map(static fn (string $dir) => self::relative($root, $dir), $directories);

        $added = [];
        $modified = [];
        $diff = self::git(['diff', '--name-status', '--no-renames', $mergeBase, '--', ...$relative], $root);
        foreach (preg_split('/\R/', $diff) ?: [] as $line) {
            if (! preg_match('/^([AM])\t(.+)$/', $line, $m) || ! str_ends_with($m[2], '.php')) {
                continue;
            }
            $path = $root.'/'.$m[2];
            if ($m[1] === 'A') {
                $added[] = $path;
            } else {
                $modified[] = $path;
            }
        }

        $untracked = self::git(['ls-files', '--others', '--exclude-standard', '--', ...$relative], $root);
        foreach (preg_split('/\R/', $untracked) ?: [] as $file) {
            if ($file !== '' && str_ends_with($file, '.php')) {
                $added[] = $root.'/'.$file;
            }
        }

        return new self(array_values(array_unique($added)), $modified);
    }

    /**
     * @param  list<string>  $paths
     */
    public static function contains(array $paths, string $path): bool
    {
        $needle = self::normalize($path);
        foreach ($paths as $candidate) {
            if (self::normalize($candidate) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function relative(string $root, string $dir): string
    {
        $root = rtrim(self::normalize($root), '/');
        $dir = self::normalize($dir);

        if ($dir === $root) {
            return '.';
        }
        if (! str_starts_with($dir, $root.'/')) {
            throw new PreflightException("Migration directory [{$dir}] is outside the git repository [{$root}].");
        }

        // Keep the original casing of the path inside the repository.
        $original = str_replace('\\', '/', (string) (realpath($dir) ?: $dir));

        return substr($original, strlen($root) + 1);
    }

    private static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', (string) (realpath($path) ?: $path));

        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }

    /**
     * @param  list<string>  $arguments
     */
    private static function git(array $arguments, string $cwd, ?string $error = null): string
    {
        $process = new Process(['git', ...$arguments], $cwd);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new PreflightException($error ?? 'git '.implode(' ', $arguments).' failed: '.trim($process->getErrorOutput()));
        }

        return trim($process->getOutput());
    }
}
