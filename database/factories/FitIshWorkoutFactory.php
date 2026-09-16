<?php

namespace Database\Factories;

use App\Models\FitIshWorkout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FitIshWorkout>
 */
class FitIshWorkoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'display_name' => strtoupper($name),
            'type' => fake()->randomElement(['resistance', 'cardio', 'hybrid']),
            'logo_path' => null,
            'logo_url' => null,
            'description' => null,
        ];
    }

    public function phoenix(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Phoenix',
            'display_name' => 'PHOENIX',
            'type' => 'resistance',
            'logo_url' => 'https://f45tv.cdn.f45.com/lionheart-logos/F45_Logos_Phoenix_600x600.png',
            'description' => null,
        ]);
    }
}
