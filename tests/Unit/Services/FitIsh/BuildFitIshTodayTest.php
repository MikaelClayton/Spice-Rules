<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\User;
use App\Services\FitIsh\BuildFitIshToday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildFitIshTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ranks_todays_sessions_by_points(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $first = User::factory()->create(['name' => 'Ada']);
        $second = User::factory()->create(['name' => 'Ben']);

        $leader = FitIshSession::factory()->create([
            'user_id' => $first->id,
            'class_date' => '2026-09-15',
            'points' => 51.2,
            'estimated_calories' => 600,
            'average_heartrate' => 140,
        ]);
        $chaser = FitIshSession::factory()->create([
            'user_id' => $second->id,
            'class_date' => '2026-09-15',
            'points' => 40.0,
            'estimated_calories' => 400,
            'average_heartrate' => 120,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $first->id,
            'class_date' => '2026-09-14',
            'points' => 99.0,
        ]);

        $board = app(BuildFitIshToday::class)->handle();

        $this->assertCount(2, $board['sessions']);
        $this->assertSame(1, $board['ranks'][$leader->id]);
        $this->assertSame(2, $board['ranks'][$chaser->id]);
        $this->assertSame(600, $board['mostCalories']);
        $this->assertSame(140, $board['highestAverageHr']);
        $this->assertTrue($board['isToday']);
        $this->assertSame('2026-09-15', $board['date']);
    }

    public function test_it_ranks_sessions_for_a_chosen_date(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->create(['name' => 'Ada']);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-15',
            'points' => 10.0,
        ]);
        $chosen = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-14',
            'points' => 99.0,
        ]);

        $board = app(BuildFitIshToday::class)->handle('2026-09-14');

        $this->assertCount(1, $board['sessions']);
        $this->assertTrue($board['sessions']->first()?->is($chosen));
        $this->assertFalse($board['isToday']);
        $this->assertSame('2026-09-14', $board['date']);
    }
}
