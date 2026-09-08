<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WicketGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WicketGroup>
 */
class WicketGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'is_tournament' => false,
        ];
    }

    public function tournament(): static
    {
        return $this->state(fn (): array => ['is_tournament' => true]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (WicketGroup $group): void {
            $group->users()->syncWithoutDetaching([$group->user_id]);
        });
    }
}
