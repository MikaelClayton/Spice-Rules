<?php

namespace Database\Factories;

use App\Models\Geoguesser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Geoguesser>
 */
class GeoguesserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => fake()->userName(),
            'ncfa' => null,
            'daily_challenge_progress' => 0,
            'daily_challenge_streak' => 0,
            'daily_challenge_current_streak' => 0,
            'is_active' => true,
        ];
    }
}
