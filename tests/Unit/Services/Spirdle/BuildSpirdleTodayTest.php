<?php

namespace Tests\Unit\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use App\Services\Spirdle\BuildSpirdleToday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSpirdleTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ranks_finished_plays_by_guesses_then_speed(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $puzzle = SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => 'crane']),
            'play_date' => '2026-09-16',
        ]);
        $fast = User::factory()->create(['name' => 'Ada']);
        $slow = User::factory()->create(['name' => 'Ben']);

        $leader = SpirdlePlay::factory()->finished(3, true, 8000)->create([
            'user_id' => $fast->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);
        $chaser = SpirdlePlay::factory()->finished(3, true, 21000)->create([
            'user_id' => $slow->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);
        SpirdlePlay::factory()->finished(2, true, 1000)->create([
            'user_id' => $fast->id,
            'spirdle_puzzle_id' => SpirdlePuzzle::factory()->create([
                'spirdle_word_id' => SpirdleWord::factory()->create(['word' => 'about']),
                'play_date' => '2026-09-15',
            ]),
        ]);

        $board = app(BuildSpirdleToday::class)->handle($fast);

        $this->assertCount(2, $board['results']);
        $this->assertTrue($board['results']->first()?->is($leader));
        $this->assertSame(1, $board['ranks'][$leader->id]);
        $this->assertSame(2, $board['ranks'][$chaser->id]);
        $this->assertTrue($board['hasPlayed']);
        $this->assertSame(8000, $board['fastestMs']);
        $this->assertSame(3, $board['fewestGuesses']);
    }
}
