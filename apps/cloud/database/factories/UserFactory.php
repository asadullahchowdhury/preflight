<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $login = Str::lower(fake()->unique()->userName());

        return [
            'github_id' => fake()->unique()->numberBetween(1_000, 99_999_999),
            'github_login' => $login,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
            'github_token' => 'gho_'.Str::random(36),
            'approved_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Signed up during the private beta and not approved yet.
     */
    public function waitlisted(): static
    {
        return $this->state(fn () => ['approved_at' => null]);
    }
}
