<?php

namespace Tests\Unit\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use App\Services\Spirdle\BuildSpirdleYou;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSpirdleYouTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_fastest_wins_and_current_streak(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create();
        $this->playOn($user, '2026-09-14', 'crane', 3, 18000);
        $this->playOn($user, '2026-09-15', 'about', 2, 5400);
        $this->playOn($user, '2026-09-16', 'world', 4, 9000);

        $you = app(BuildSpirdleYou::class)->handle($user);

        $this->assertTrue($you['hasData']);
        $this->assertSame(3, $you['stats']['played']);
        $this->assertSame(3, $you['stats']['wins']);
        $this->assertSame(3, $you['stats']['currentStreak']);
        $this->assertSame(3, $you['stats']['longestStreak']);
        $this->assertSame(5400, $you['stats']['fastestMs']);
        $this->assertSame('5s', $you['stats']['fastestLabel']);
        $this->assertSame('2026-09-15', $you['fastest'][0]['date']);
        $this->assertSame(3.0, $you['stats']['averageGuesses']);
    }

    private function playOn(User $user, string $date, string $word, int $guesses, int $durationMs): void
    {
        $puzzle = SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => $word]),
            'play_date' => $date,
        ]);

        SpirdlePlay::factory()->finished($guesses, true, $durationMs)->create([
            'user_id' => $user->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);
    }
}
