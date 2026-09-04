<?php

namespace Database\Factories;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeoguesserChallenge>
 */
class GeoguesserChallengeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'geoguesser_id' => Geoguesser::factory(),
            'attempted_at' => now(),
            'challenge_token' => fake()->bothify('????????????????'),
            'total_score' => fake()->numberBetween(0, 25000),
            'geoguesser_guid' => fake()->uuid(),
            'total_distance' => fake()->numberBetween(0, 20000000),
            'total_steps_count' => fake()->numberBetween(0, 20000),
            'progress' => null,
        ];
    }
}
