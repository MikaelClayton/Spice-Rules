<?php

namespace Database\Factories;

use App\Models\SpirdleWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpirdleWord>
 */
class SpirdleWordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'word' => fake()->unique()->regexify('[a-z]{5}'),
            'is_answer' => true,
        ];
    }

    public function guessOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_answer' => false,
        ]);
    }
}
