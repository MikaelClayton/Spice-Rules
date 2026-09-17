<?php

namespace Tests\Unit\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use App\Services\Spirdle\BuildSpirdleChallenges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSpirdleChallengesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_omits_days_the_viewer_has_not_finished(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Ada']);
        $today = $this->puzzleOn('2026-09-16', 'crane');
        $past = $this->puzzleOn('2026-09-15', 'about');

        SpirdlePlay::factory()->finished(3, true, 8000, 2)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $today->id,
        ]);
        SpirdlePlay::factory()->finished(4, true, 12000)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $past->id,
        ]);
        SpirdlePlay::factory()->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $today->id,
            'guess_count' => 1,
        ]);

        $dailies = app(BuildSpirdleChallenges::class)->handle($viewer);

        $this->assertSame([], $dailies);
    }

    public function test_it_only_includes_days_the_viewer_finished(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create(['name' => 'Ben']);
        $other = User::factory()->create(['name' => 'Ada']);
        $today = $this->puzzleOn('2026-09-16', 'crane');
        $past = $this->puzzleOn('2026-09-15', 'about');

        SpirdlePlay::factory()->finished(3, true, 8000)->create([
            'user_id' => $other->id,
            'spirdle_puzzle_id' => $today->id,
        ]);
        SpirdlePlay::factory()->finished(4, true, 12000)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $past->id,
        ]);

        $dailies = app(BuildSpirdleChallenges::class)->handle($viewer);

        $this->assertCount(1, $dailies);
        $this->assertSame('2026-09-15', $dailies[0]['date']);
        $this->assertSame('about', $dailies[0]['word']);
        $this->assertFalse($dailies[0]['locked']);
    }

    public function test_it_lists_attempts_misses_times_and_the_word_after_a_finish(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $viewer = User::factory()->create(['name' => 'Ben']);
        $puzzle = $this->puzzleOn('2026-09-16', 'crane');

        SpirdlePlay::factory()->finished(2, true, 5400, 1)->create([
            'user_id' => $viewer->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);

        $dailies = app(BuildSpirdleChallenges::class)->handle($viewer);

        $this->assertFalse($dailies[0]['locked']);
        $this->assertSame('crane', $dailies[0]['word']);
        $this->assertSame('Ben', $dailies[0]['standings'][0]['label']);
        $this->assertSame(2, $dailies[0]['standings'][0]['attempts']);
        $this->assertSame(1, $dailies[0]['standings'][0]['missed']);
        $this->assertSame('5s', $dailies[0]['standings'][0]['time']);
        $this->assertSame('crane', $dailies[0]['standings'][0]['word']);
        $this->assertTrue($dailies[0]['standings'][0]['won']);
        $this->assertNotEmpty($dailies[0]['board']['results']);
    }

    private function puzzleOn(string $date, string $word): SpirdlePuzzle
    {
        return SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => $word]),
            'play_date' => $date,
        ]);
    }
}
