<?php

namespace Database\Factories;

use App\Models\GeoguesserChallenge;
use App\Models\GeoguesserRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeoguesserRound>
 */
class GeoguesserRoundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'geoguesser_challenge_id' => GeoguesserChallenge::factory(),
            'round_number' => fake()->numberBetween(1, 5),
            'actual_lat' => fake()->latitude(),
            'actual_lng' => fake()->longitude(),
            'guess_lat' => fake()->latitude(),
            'guess_lng' => fake()->longitude(),
            'score' => fake()->numberBetween(0, 5000),
            'percentage' => fake()->randomFloat(2, 0, 100),
            'time' => fake()->numberBetween(5, 180),
            'steps_count' => fake()->numberBetween(0, 50),
            'distance_in_meters' => fake()->numberBetween(0, 5_000_000),
            'timed_out' => false,
            'timed_out_with_guess' => false,
            'skipped_round' => false,
            'country_code' => fake()->countryCode(),
        ];
    }
}
