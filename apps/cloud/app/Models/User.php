<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A person signed in with GitHub.
 *
 * @property int $id
 * @property int $github_id
 * @property string $github_login
 * @property string $name
 * @property string|null $email
 * @property string|null $avatar_url
 * @property string|null $github_token
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['github_id', 'github_login', 'name', 'email', 'avatar_url', 'github_token'])]
#[Hidden(['github_token', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_id' => 'integer',
            'github_token' => 'encrypted',
        ];
    }

    /**
     * @return BelongsToMany<Installation, $this>
     */
    public function installations(): BelongsToMany
    {
        return $this->belongsToMany(Installation::class);
    }

    /**
     * Repositories of every installation the user can see.
     *
     * @return Builder<Repository>
     */
    public function repositories(): Builder
    {
        return Repository::query()->whereIn('installation_id', $this->installations()->select('installations.id'));
    }
}
