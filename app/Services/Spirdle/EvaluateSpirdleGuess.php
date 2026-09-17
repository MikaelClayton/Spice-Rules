<?php

namespace App\Services\Spirdle;

use App\Enums\SpirdleTile;
use App\Models\SpirdlePlay;

class EvaluateSpirdleGuess
{
    /**
     * @return list<SpirdleTile>
     */
    public function handle(string $guess, string $answer): array
    {
        $guess = strtolower($guess);
        $answer = strtolower($answer);
        $tiles = array_fill(0, SpirdlePlay::WORD_LENGTH, SpirdleTile::Absent);
        $remaining = str_split($answer);

        for ($index = 0; $index < SpirdlePlay::WORD_LENGTH; $index++) {
            if (($guess[$index] ?? '') !== ($answer[$index] ?? '')) {
                continue;
            }

            $tiles[$index] = SpirdleTile::Correct;
            $remaining[$index] = null;
        }

        for ($index = 0; $index < SpirdlePlay::WORD_LENGTH; $index++) {
            if ($tiles[$index] === SpirdleTile::Correct) {
                continue;
            }

            $letter = $guess[$index] ?? '';
            $position = array_search($letter, $remaining, true);

            if ($position === false) {
                continue;
            }

            $tiles[$index] = SpirdleTile::Present;
            $remaining[$position] = null;
        }

        return array_values($tiles);
    }
}
