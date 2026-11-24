<?php

namespace Database\Factories;

use App\Models\Installation;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
{
    protected $model = Repository::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installation_id' => Installation::factory(),
            'github_id' => fake()->unique()->numberBetween(1_000, 999_999_999),
            'full_name' => Str::lower(fake()->unique()->userName()).'/'.Str::slug(implode(' ', (array) fake()->words(2))),
            'private' => true,
        ];
    }
}
