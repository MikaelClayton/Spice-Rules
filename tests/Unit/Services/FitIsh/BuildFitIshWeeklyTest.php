<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\User;
use App\Services\FitIsh\BuildFitIshWeekly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildFitIshWeeklyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_totals_points_for_the_sunday_week(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->create(['name' => 'Ada']);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-14',
            'points' => 40.0,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-15',
            'points' => 10.5,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-06',
            'points' => 80.0,
        ]);

        $weekly = app(BuildFitIshWeekly::class)->payload();

        $this->assertTrue($weekly['isCurrent']);
        $this->assertSame('2026-09-13', $weekly['start']);
        $this->assertCount(1, $weekly['standings']);
        $this->assertSame(50.5, $weekly['standings'][0]['total']);
        $this->assertSame(2, $weekly['standings'][0]['played']);
        $this->assertSame('Ada', $weekly['standings'][0]['label']);
    }
}
