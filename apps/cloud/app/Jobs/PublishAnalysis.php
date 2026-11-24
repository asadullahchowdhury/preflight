<?php

namespace App\Jobs;

use App\GitHub\GitHubApp;
use App\Models\Analysis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Preflight\Core\Render\MarkdownRenderer;
use Throwable;

/**
 * Posts an analysis to GitHub as the Preflight App: a check run on the PR's head commit, and one PR comment
 * that is updated on every push.
 */
class PublishAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public Analysis $analysis) {}

    public function handle(GitHubApp $github): void
    {
        $analysis = $this->analysis->loadMissing('repository.installation');
        $repository = $analysis->repository;
        $client = $github->asInstallation($repository->installation->github_id);
        $markdown = $this->markdown($analysis);

        $checkRun = $client->post("/repos/{$repository->full_name}/check-runs", [
            'name' => 'Preflight',
            'head_sha' => $analysis->head_sha,
            'status' => 'completed',
            'conclusion' => match ($analysis->severity) {
                'danger' => 'failure',
                'warn' => 'neutral',
                default => 'success',
            },
            'details_url' => route('analyses.show', $analysis),
            'output' => [
                'title' => $this->title($analysis),
                'summary' => Str::limit($markdown, 60_000, "\n\n…"),
                'annotations' => $this->annotations($analysis),
            ],
        ])->throw();

        $commentId = $analysis->pr_number !== null ? $this->comment($github, $analysis, $markdown) : null;

        $analysis->update([
            'status' => Analysis::COMPLETED,
            'check_run_id' => $checkRun->json('id'),
            'comment_id' => $commentId,
            'error' => null,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        $this->analysis->update(['status' => Analysis::FAILED, 'error' => $e?->getMessage()]);
    }

    private function comment(GitHubApp $github, Analysis $analysis, string $markdown): ?int
    {
        $repository = $analysis->repository;
        $client = $github->asInstallation($repository->installation->github_id);
        $path = "/repos/{$repository->full_name}/issues/{$analysis->pr_number}/comments";

        $existing = null;
        $page = 1;
        do {
            $comments = $client->get($path, ['per_page' => 100, 'page' => $page])->throw()->json();
            $comments = is_array($comments) ? $comments : [];
            foreach ($comments as $comment) {
                if (is_array($comment) && str_starts_with((string) ($comment['body'] ?? ''), MarkdownRenderer::MARKER)) {
                    $existing = (int) $comment['id'];
                    break 2;
                }
            }
            $page++;
        } while (count($comments) === 100);

        $body = Str::limit($markdown, 60_000, "\n\n…");

        if ($existing !== null) {
            $client->patch("/repos/{$repository->full_name}/issues/comments/{$existing}", ['body' => $body])->throw();

            return $existing;
        }

        // Stay quiet on PRs where nothing needs attention.
        if ($analysis->severity === 'info') {
            return null;
        }

        return (int) $client->post($path, ['body' => $body])->throw()->json('id');
    }

    private function markdown(Analysis $analysis): string
    {
        $markdown = (string) Arr::get($analysis->report ?? [], 'markdown', '');
        foreach ((array) Arr::get($analysis->report ?? [], 'notes', []) as $note) {
            $markdown .= "\n> [!NOTE]\n> ".$note."\n";
        }

        return $markdown."\n<sub>[Open in Preflight](".route('analyses.show', $analysis).')</sub>'."\n";
    }

    private function title(Analysis $analysis): string
    {
        return match ($analysis->severity) {
            'danger' => $analysis->danger_count.' risky '.Str::plural('statement', $analysis->danger_count),
            'warn' => $analysis->warn_count.' '.Str::plural('warning', $analysis->warn_count),
            default => $analysis->statement_count === 0 ? 'No migration changes' : 'Migrations look safe',
        };
    }

    /**
     * Check run annotations (GitHub accepts 50 per request) on the migration files.
     *
     * @return list<array<string, mixed>>
     */
    private function annotations(Analysis $analysis): array
    {
        $files = (array) Arr::get($analysis->report ?? [], 'files', []);
        $annotations = [];

        foreach ((array) Arr::get($analysis->report ?? [], 'statements', []) as $statement) {
            if (! is_array($statement) || ($statement['severity'] ?? 'info') === 'info') {
                continue;
            }
            $path = $files[$statement['migration'] ?? ''] ?? null;
            if (! is_string($path)) {
                continue;
            }
            $finding = collect((array) ($statement['findings'] ?? []))
                ->sortByDesc(fn ($f) => ['info' => 0, 'warn' => 1, 'danger' => 2][$f['severity'] ?? 'info'] ?? 0)
                ->first();

            $annotations[] = [
                'path' => $path,
                'start_line' => 1,
                'end_line' => 1,
                'annotation_level' => $statement['severity'] === 'danger' ? 'failure' : 'warning',
                'title' => Str::limit(trim(($statement['table'] ?? '').' · '.($statement['algorithm'] ?? '').($statement['blocks_writes'] ? ' · blocks writes' : ''), ' ·'), 250),
                'message' => Str::limit(trim(($statement['sql'] ?? '')."\n\n".($finding['message'] ?? '')), 60_000),
                'raw_details' => is_string($finding['suggestion'] ?? null) ? $finding['suggestion'] : null,
            ];

            if (count($annotations) === 50) {
                break;
            }
        }

        return array_map(static fn (array $a) => array_filter($a, static fn ($v) => $v !== null), $annotations);
    }
}
