<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshStudio;
use App\Models\FitIshWorkout;
use App\Models\User;
use App\Services\FitIsh\BuildFitIshSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildFitIshSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_synced_class_dates_newest_first(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $user = User::factory()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $phoenix = FitIshWorkout::factory()->phoenix()->create();
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'fit_ish_workout_id' => $phoenix->id,
            'class_date' => '2026-09-14',
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'fit_ish_workout_id' => $phoenix->id,
            'class_date' => '2026-09-15',
        ]);

        $this->assertSame([
            ['date' => '2026-09-15', 'label' => 'Sep 15, 2026'],
            ['date' => '2026-09-14', 'label' => 'Sep 14, 2026'],
        ], app(BuildFitIshSessions::class)->dates());
    }
}
