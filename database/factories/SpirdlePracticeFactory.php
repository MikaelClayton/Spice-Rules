<?php

namespace Database\Factories;

use App\Models\SpirdlePractice;
use App\Models\SpirdleWord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpirdlePractice>
 */
class SpirdlePracticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'spirdle_word_id' => SpirdleWord::factory(),
            'guesses' => [],
            'guess_count' => 0,
            'invalid_word_count' => 0,
            'duration_ms' => null,
            'accumulated_ms' => 0,
            'won' => false,
            'started_at' => now(),
            'running_since' => now(),
            'finished_at' => null,
        ];
    }

    public function finished(int $guesses = 3, bool $won = true, int $durationMs = 12400, int $invalid = 0): static
    {
        return $this->state(function (array $attributes) use ($guesses, $won, $durationMs, $invalid): array {
            $rows = [];

            for ($index = 0; $index < $guesses; $index++) {
                $isLast = $index === $guesses - 1;
                $tiles = $won && $isLast
                    ? array_fill(0, SpirdlePractice::WORD_LENGTH, 'correct')
                    : array_fill(0, SpirdlePractice::WORD_LENGTH, 'absent');

                $rows[] = [
                    'word' => 'try'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'tiles' => $tiles,
                ];
            }

            return [
                'guesses' => $rows,
                'guess_count' => $guesses,
                'invalid_word_count' => $invalid,
                'duration_ms' => $durationMs,
                'accumulated_ms' => $durationMs,
                'won' => $won,
                'running_since' => null,
                'finished_at' => now(),
            ];
        });
    }
}
