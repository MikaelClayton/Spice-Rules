<?php

namespace Database\Factories;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCustomDrink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PubGolfCustomDrink>
 */
class PubGolfCustomDrinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true).' special',
            'category' => PubGolfDrinkCategory::Beer,
            'photo_path' => null,
            'removed_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'removed_at' => now(),
            'photo_path' => null,
        ]);
    }
}
