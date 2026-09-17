<?php

namespace Database\Factories;

use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpirdlePuzzle>
 */
class SpirdlePuzzleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spirdle_word_id' => SpirdleWord::factory(),
            'play_date' => today()->toDateString(),
        ];
    }
}
