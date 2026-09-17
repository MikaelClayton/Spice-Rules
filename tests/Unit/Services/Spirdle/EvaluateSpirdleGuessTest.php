<?php

namespace Tests\Unit\Services\Spirdle;

use App\Enums\SpirdleTile;
use App\Services\Spirdle\EvaluateSpirdleGuess;
use Tests\TestCase;

class EvaluateSpirdleGuessTest extends TestCase
{
    public function test_it_marks_exact_matches_correct(): void
    {
        $tiles = (new EvaluateSpirdleGuess)->handle('crane', 'crane');

        $this->assertSame(
            [SpirdleTile::Correct, SpirdleTile::Correct, SpirdleTile::Correct, SpirdleTile::Correct, SpirdleTile::Correct],
            $tiles,
        );
    }

    public function test_it_marks_missing_letters_absent(): void
    {
        $tiles = (new EvaluateSpirdleGuess)->handle('xyzzy', 'crane');

        $this->assertSame(
            [SpirdleTile::Absent, SpirdleTile::Absent, SpirdleTile::Absent, SpirdleTile::Absent, SpirdleTile::Absent],
            $tiles,
        );
    }

    public function test_it_marks_present_letters_after_greens(): void
    {
        $tiles = (new EvaluateSpirdleGuess)->handle('three', 'there');

        $this->assertSame(
            [SpirdleTile::Correct, SpirdleTile::Correct, SpirdleTile::Present, SpirdleTile::Present, SpirdleTile::Correct],
            $tiles,
        );
    }

    public function test_it_does_not_reuse_a_letter_already_spent_on_green(): void
    {
        $tiles = (new EvaluateSpirdleGuess)->handle('beads', 'abbey');

        $this->assertSame(
            [SpirdleTile::Present, SpirdleTile::Present, SpirdleTile::Present, SpirdleTile::Absent, SpirdleTile::Absent],
            $tiles,
        );
    }
}
