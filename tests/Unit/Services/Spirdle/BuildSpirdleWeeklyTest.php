<?php

namespace Tests\Unit\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\SpirdleWord;
use App\Models\User;
use App\Services\Spirdle\BuildSpirdleWeekly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSpirdleWeeklyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scores_sunday_week_points_from_guess_counts(): void
    {
        $this->travelTo('2026-09-16 12:00:00');

        $user = User::factory()->create(['name' => 'Ada']);
        $this->playOn($user, '2026-09-14', 'crane', 2);
        $this->playOn($user, '2026-09-16', 'about', 4);
        $this->playOn($user, '2026-09-06', 'world', 1);

        $weekly = app(BuildSpirdleWeekly::class)->payload();

        $this->assertTrue($weekly['isCurrent']);
        $this->assertSame('2026-09-13', $weekly['start']);
        $this->assertCount(1, $weekly['standings']);
        $this->assertSame(8, $weekly['standings'][0]['total']);
        $this->assertSame(2, $weekly['standings'][0]['played']);
        $this->assertSame('Ada', $weekly['standings'][0]['label']);
    }

    private function playOn(User $user, string $date, string $word, int $guesses): void
    {
        $puzzle = SpirdlePuzzle::factory()->create([
            'spirdle_word_id' => SpirdleWord::factory()->create(['word' => $word]),
            'play_date' => $date,
        ]);

        SpirdlePlay::factory()->finished($guesses, true, 12000)->create([
            'user_id' => $user->id,
            'spirdle_puzzle_id' => $puzzle->id,
        ]);
    }
}
