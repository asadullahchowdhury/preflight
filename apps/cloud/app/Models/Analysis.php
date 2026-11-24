<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of preflight:check, sent from a repository's CI.
 *
 * @property int $id
 * @property int $repository_id
 * @property int|null $pr_number
 * @property string $head_sha
 * @property string|null $base_ref
 * @property string|null $ref
 * @property string|null $actor
 * @property string|null $run_url
 * @property string $status
 * @property string|null $severity
 * @property string $mysql_version
 * @property bool $size_aware
 * @property int $statement_count
 * @property int $danger_count
 * @property int $warn_count
 * @property list<string> $migrations
 * @property array<string, mixed>|null $report
 * @property int|null $check_run_id
 * @property int|null $comment_id
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Repository $repository
 */
#[Fillable([
    'repository_id', 'pr_number', 'head_sha', 'base_ref', 'ref', 'actor', 'run_url', 'status', 'severity',
    'mysql_version', 'size_aware', 'statement_count', 'danger_count', 'warn_count', 'migrations', 'report',
    'check_run_id', 'comment_id', 'error',
])]
class Analysis extends Model
{
    public const RECEIVED = 'received';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pr_number' => 'integer',
            'size_aware' => 'boolean',
            'statement_count' => 'integer',
            'danger_count' => 'integer',
            'warn_count' => 'integer',
            'migrations' => 'array',
            'report' => 'array',
            'check_run_id' => 'integer',
            'comment_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function pullRequestUrl(): ?string
    {
        return $this->pr_number !== null ? 'https://github.com/'.$this->repository->full_name.'/pull/'.$this->pr_number : null;
    }
}
