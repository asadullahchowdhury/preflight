<?php

namespace Database\Factories;

use App\Models\Installation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Installation>
 */
class InstallationFactory extends Factory
{
    protected $model = Installation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_id' => fake()->unique()->numberBetween(1_000, 99_999_999),
            'account_id' => fake()->unique()->numberBetween(1_000, 99_999_999),
            'account_login' => Str::lower(fake()->unique()->userName()),
            'account_type' => 'Organization',
            'account_avatar_url' => null,
            'repository_selection' => 'selected',
        ];
    }
}
