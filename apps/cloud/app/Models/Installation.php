<?php

namespace App\Models;

use Database\Factories\InstallationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The GitHub App installed on a user or organization account.
 *
 * @property int $id
 * @property int $github_id
 * @property int $account_id
 * @property string $account_login
 * @property string $account_type
 * @property string|null $account_avatar_url
 * @property string $repository_selection
 * @property Carbon|null $suspended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['github_id', 'account_id', 'account_login', 'account_type', 'account_avatar_url', 'repository_selection', 'suspended_at'])]
class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_id' => 'integer',
            'account_id' => 'integer',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->suspended_at === null;
    }
}
