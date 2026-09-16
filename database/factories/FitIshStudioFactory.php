<?php

namespace Database\Factories;

use App\Models\FitIshStudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FitIshStudio>
 */
class FitIshStudioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1000, 99999),
            'name' => 'F45 '.fake()->unique()->city(),
            'code' => fake()->unique()->regexify('[a-z0-9]{4}'),
            'timezone' => 'Africa/Johannesburg',
            'is_loaner' => false,
        ];
    }

    public function faerieGlen(): static
    {
        return $this->state(fn (array $attributes): array => [
            'external_id' => 5061,
            'name' => 'F45 Faerie Glen',
            'code' => 'ojb7',
            'timezone' => 'Africa/Johannesburg',
            'is_loaner' => false,
        ]);
    }
}
