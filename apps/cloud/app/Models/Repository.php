<?php

namespace App\Models;

use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Preflight\Core\Stats\StatsSet;

/**
 * @property int $id
 * @property int $installation_id
 * @property int $github_id
 * @property string $full_name
 * @property bool $private
 * @property array<string, mixed>|null $settings
 * @property string|null $stats_token_hash
 * @property Carbon|null $stats_token_created_at
 * @property Carbon|null $stats_pushed_at
 * @property Carbon|null $stats_captured_on
 * @property string|null $stats_server_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Installation $installation
 */
#[Fillable(['installation_id', 'github_id', 'full_name', 'private', 'settings', 'stats_pushed_at', 'stats_captured_on', 'stats_server_version'])]
#[Hidden(['stats_token_hash'])]
class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_id' => 'integer',
            'private' => 'boolean',
            'settings' => 'array',
            'stats_token_created_at' => 'datetime',
            'stats_pushed_at' => 'datetime',
            'stats_captured_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Installation, $this>
     */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * @return HasOne<Analysis, $this>
     */
    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(Analysis::class)->latestOfMany();
    }

    public function owner(): string
    {
        return explode('/', $this->full_name, 2)[0];
    }

    public function name(): string
    {
        return explode('/', $this->full_name, 2)[1] ?? $this->full_name;
    }

    /**
     * @return HasMany<TableStat, $this>
     */
    public function tableStats(): HasMany
    {
        return $this->hasMany(TableStat::class);
    }

    /**
     * Production table sizes from the most recent push, for size-aware analyses.
     */
    public function latestStats(): ?StatsSet
    {
        if ($this->stats_captured_on === null) {
            return null;
        }

        $rows = $this->tableStats()->whereDate('captured_on', $this->stats_captured_on)->get();
        if ($rows->isEmpty()) {
            return null;
        }

        return StatsSet::fromArray($rows->mapWithKeys(fn (TableStat $s) => [
            $s->table_name => ['rows' => $s->rows, 'data_bytes' => $s->data_bytes, 'index_bytes' => $s->index_bytes],
        ])->all());
    }

    /**
     * Creates a new push token (replacing any old one). The plain token is returned once; only its hash is kept.
     */
    public function issueStatsToken(): string
    {
        $token = 'pfs_'.Str::random(40);
        $this->forceFill(['stats_token_hash' => hash('sha256', $token), 'stats_token_created_at' => now()])->save();

        return $token;
    }

    public static function forStatsToken(string $token): ?self
    {
        return str_starts_with($token, 'pfs_')
            ? self::query()->where('stats_token_hash', hash('sha256', $token))->first()
            : null;
    }
}
