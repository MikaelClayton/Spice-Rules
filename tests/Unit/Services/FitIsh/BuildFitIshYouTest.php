<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshSessionGraphPoint;
use App\Models\FitIshSessionZone;
use App\Models\FitIshWorkout;
use App\Models\User;
use App\Services\FitIsh\BuildFitIshYou;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildFitIshYouTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarises_streak_calories_and_workout_averages(): void
    {
        $this->travelTo('2026-09-16 08:00:00');

        $user = User::factory()->create();
        $other = User::factory()->create();
        $abacus = FitIshWorkout::factory()->create([
            'name' => 'Abacus',
            'display_name' => 'Abacus',
            'type' => 'resistance',
        ]);
        $drift = FitIshWorkout::factory()->create([
            'name' => 'Drift',
            'display_name' => 'Drift',
            'type' => 'cardio',
        ]);

        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $abacus->id,
            'class_date' => '2026-09-14',
            'points' => 47.5,
            'estimated_calories' => 550,
            'average_heartrate' => 150,
            'max_heartrate' => 175,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $abacus->id,
            'class_date' => '2026-09-15',
            'points' => 40.0,
            'estimated_calories' => 400,
            'average_heartrate' => 130,
            'max_heartrate' => 160,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $drift->id,
            'class_date' => '2026-09-16',
            'points' => 47.2,
            'estimated_calories' => 580,
            'average_heartrate' => 153,
            'max_heartrate' => 180,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $drift->id,
            'class_date' => '2026-09-12',
            'points' => 30.0,
            'estimated_calories' => 300,
            'average_heartrate' => 120,
            'max_heartrate' => 150,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $other->id,
            'fit_ish_workout_id' => $abacus->id,
            'class_date' => '2026-09-16',
            'points' => 99.0,
            'estimated_calories' => 900,
        ]);

        $you = app(BuildFitIshYou::class)->handle($user);

        $this->assertTrue($you['hasData']);
        $this->assertSame(4, $you['stats']['classes']);
        $this->assertSame(3, $you['stats']['longestStreak']);
        $this->assertSame(1830, $you['stats']['totalCalories']);
        $this->assertSame(580, $you['stats']['maxCalories']);
        $this->assertSame('Abacus', $you['workouts'][0]['name']);
        $this->assertSame(43.8, $you['workouts'][0]['points']);
        $this->assertSame(2, $you['workouts'][0]['count']);
        $this->assertSame('Drift', $you['workouts'][1]['name']);
        $this->assertNotSame($you['workouts'][0]['pointsStyle'], $you['workouts'][1]['pointsStyle']);
        $this->assertCount(4, $you['heartrate']['columns']);
        $this->assertNotSame([], $you['monthly']['datasets']);
    }

    public function test_heartrate_columns_use_recorded_minute_range(): void
    {
        $this->travelTo('2026-09-16 08:00:00');

        $user = User::factory()->create();
        $session = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-16',
            'average_heartrate' => 140,
            'max_heartrate' => 170,
        ]);
        FitIshSessionGraphPoint::factory()->create([
            'fit_ish_session_id' => $session->id,
            'minute' => 4,
            'bpm_min' => 88,
            'bpm_max' => 110,
        ]);
        FitIshSessionGraphPoint::factory()->create([
            'fit_ish_session_id' => $session->id,
            'minute' => 20,
            'bpm_min' => 150,
            'bpm_max' => 188,
        ]);

        $you = app(BuildFitIshYou::class)->handle($user);

        $this->assertSame(88, $you['heartrate']['columns'][0]['min']);
        $this->assertSame(188, $you['heartrate']['columns'][0]['max']);
        $this->assertSame(140, $you['heartrate']['columns'][0]['average']);
    }

    public function test_heatmap_marks_class_days_and_skips_other_people(): void
    {
        $this->travelTo('2026-09-16 08:00:00');

        $user = User::factory()->create();
        $other = User::factory()->create();
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'class_date' => '2026-09-16',
            'points' => 48.0,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $other->id,
            'class_date' => '2026-09-15',
            'points' => 99.0,
        ]);

        $you = app(BuildFitIshYou::class)->handle($user);
        $cells = collect($you['heatmap']['weeks'])->flatten(1);
        $today = $cells->firstWhere('date', '2026-09-16');
        $otherDay = $cells->firstWhere('date', '2026-09-15');

        $this->assertNotNull($today);
        $this->assertGreaterThan(0, $today['level']);
        $this->assertSame(0, $otherDay['level']);
        $this->assertStringContainsString('48.0 pts', $today['title']);
    }

    public function test_type_charts_average_zone_share_per_workout_type(): void
    {
        $user = User::factory()->create();
        $resistance = FitIshWorkout::factory()->create(['type' => 'resistance']);
        $cardio = FitIshWorkout::factory()->create(['type' => 'cardio']);
        $hard = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $resistance->id,
        ]);
        $easy = FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_workout_id' => $cardio->id,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $hard->id,
            'zone_number' => 1,
            'percentage_value' => 10,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $hard->id,
            'zone_number' => 5,
            'percentage_value' => 40,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $easy->id,
            'zone_number' => 1,
            'percentage_value' => 80,
        ]);
        FitIshSessionZone::factory()->create([
            'fit_ish_session_id' => $easy->id,
            'zone_number' => 5,
            'percentage_value' => 5,
        ]);

        $you = app(BuildFitIshYou::class)->handle($user);

        $this->assertSame(['Zone 1', 'Zone 2', 'Zone 3', 'Zone 4', 'Zone 5'], $you['typeRadar']['labels']);
        $this->assertSame('Resistance', $you['typeRadar']['datasets'][0]['label']);
        $this->assertSame(10.0, $you['typeRadar']['datasets'][0]['values'][0]);
        $this->assertSame(40.0, $you['typeRadar']['datasets'][0]['values'][4]);
        $this->assertSame('Cardio', $you['typeRadar']['datasets'][1]['label']);
        $this->assertSame(80.0, $you['typeRadar']['datasets'][1]['values'][0]);
        $this->assertSame(['Resistance', 'Cardio'], $you['zoneStacks']['labels']);
        $this->assertSame([10.0, 80.0], $you['zoneStacks']['datasets'][0]['values']);
    }
}
