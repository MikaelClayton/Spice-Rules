<?php

namespace Tests\Unit\Services\Spirdle;

use App\Models\SpirdlePractice;
use App\Models\SpirdleWord;
use App\Models\User;
use App\Services\Spirdle\BuildSpirdlePracticeStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSpirdlePracticeStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_average_time_pace_band_and_struggle_words(): void
    {
        $fast = User::factory()->create();
        $mid = User::factory()->create();
        $slow = User::factory()->create();

        $this->practice($fast, 'crane', 2, true, 4000);
        $this->practice($fast, 'about', 3, true, 6000);

        $this->practice($mid, 'world', 4, true, 12000);
        $this->practice($mid, 'plant', 5, true, 14000);
        $this->practice($mid, 'zzzzz', 6, false, 20000);

        $this->practice($slow, 'music', 6, true, 30000);

        $stats = app(BuildSpirdlePracticeStats::class)->handle($mid);

        $this->assertTrue($stats['hasData']);
        $this->assertSame(3, $stats['stats']['played']);
        $this->assertSame(2, $stats['stats']['wins']);
        $this->assertSame(15333, $stats['stats']['averageDurationMs']);
        $this->assertSame('15s', $stats['stats']['averageDurationLabel']);
        $this->assertTrue($stats['pace']['available']);
        $this->assertSame(50, $stats['pace']['fasterThanPercent']);
        $this->assertSame('top 50%', $stats['pace']['band']);
        $this->assertSame(['ZZZZZ', 'PLANT'], array_column($stats['struggleWords'], 'word'));
        $this->assertFalse($stats['struggleWords'][0]['won']);
        $this->assertSame(6, $stats['struggleWords'][0]['guesses']);
        $this->assertSame(1, $stats['struggleWords'][0]['missCount']);
        $this->assertTrue($stats['struggleWords'][1]['won']);
        $this->assertSame(5, $stats['struggleWords'][1]['guesses']);
        $this->assertSame(0, $stats['stats']['missedMoreThanOnce']);
    }

    public function test_it_counts_words_missed_more_than_once(): void
    {
        $user = User::factory()->create();
        $repeat = SpirdleWord::factory()->create(['word' => 'crane']);
        $once = SpirdleWord::factory()->create(['word' => 'about']);

        $this->practice($user, $repeat, 6, false, 20000);
        $this->practice($user, $repeat, 6, false, 22000);
        $this->practice($user, $once, 6, false, 18000);
        $this->practice($user, 'plant', 5, true, 14000);

        $stats = app(BuildSpirdlePracticeStats::class)->handle($user);

        $this->assertSame(1, $stats['stats']['missedMoreThanOnce']);
        $this->assertSame('CRANE', $stats['struggleWords'][0]['word']);
        $this->assertSame(2, $stats['struggleWords'][0]['missCount']);
        $this->assertSame('ABOUT', $stats['struggleWords'][1]['word']);
        $this->assertSame(1, $stats['struggleWords'][1]['missCount']);
    }

    public function test_pace_marks_fastest_player_in_top_10_percent(): void
    {
        $fast = User::factory()->create();
        $others = User::factory()->count(9)->create();

        $this->practice($fast, 'crane', 2, true, 3000);

        foreach ($others as $index => $user) {
            $this->practice($user, 'word'.$index, 4, true, 20000 + ($index * 1000));
        }

        $stats = app(BuildSpirdlePracticeStats::class)->handle($fast);

        $this->assertTrue($stats['pace']['available']);
        $this->assertSame(100, $stats['pace']['fasterThanPercent']);
        $this->assertSame('top 10%', $stats['pace']['band']);
    }

    public function test_pace_marks_slowest_player_in_last_10_percent(): void
    {
        $slow = User::factory()->create();
        $others = User::factory()->count(9)->create();

        $this->practice($slow, 'crane', 6, true, 90000);

        foreach ($others as $index => $user) {
            $this->practice($user, 'word'.$index, 3, true, 5000 + ($index * 100));
        }

        $stats = app(BuildSpirdlePracticeStats::class)->handle($slow);

        $this->assertTrue($stats['pace']['available']);
        $this->assertSame(0, $stats['pace']['fasterThanPercent']);
        $this->assertSame('last 10%', $stats['pace']['band']);
    }

    private function practice(User $user, SpirdleWord|string $word, int $guesses, bool $won, int $durationMs): void
    {
        $wordId = $word instanceof SpirdleWord
            ? $word->id
            : SpirdleWord::factory()->create(['word' => $word])->id;

        SpirdlePractice::factory()->finished($guesses, $won, $durationMs)->create([
            'user_id' => $user->id,
            'spirdle_word_id' => $wordId,
        ]);
    }
}
