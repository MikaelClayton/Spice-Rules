<?php

namespace Tests\Unit\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshWorkout;
use App\Models\User;
use App\Services\FitIsh\BuildFitIshWorkouts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildFitIshWorkoutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_workouts_by_synced_class_count_and_best_score(): void
    {
        $this->travelTo('2026-09-15 12:00:00');

        $ada = User::factory()->create();
        $ben = User::factory()->create();
        $phoenix = FitIshWorkout::factory()->phoenix()->create();
        $hollywood = FitIshWorkout::factory()->create([
            'name' => 'Hollywood',
            'display_name' => 'HOLLYWOOD',
            'type' => 'cardio',
        ]);
        FitIshSession::factory()->create([
            'user_id' => $ada->id,
            'fit_ish_workout_id' => $phoenix->id,
            'class_date' => '2026-09-14',
            'points' => 40.0,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $ben->id,
            'fit_ish_workout_id' => $phoenix->id,
            'class_date' => '2026-09-15',
            'points' => 51.2,
        ]);
        FitIshSession::factory()->create([
            'user_id' => $ada->id,
            'fit_ish_workout_id' => $hollywood->id,
            'class_date' => '2026-09-13',
            'points' => 33.0,
        ]);

        $workouts = app(BuildFitIshWorkouts::class)->handle();

        $this->assertSame('PHOENIX', $workouts[0]['displayName']);
        $this->assertSame('resistance', $workouts[0]['type']);
        $this->assertSame(2, $workouts[0]['sessions']);
        $this->assertSame(2, $workouts[0]['people']);
        $this->assertSame(51.2, $workouts[0]['best']);
        $this->assertSame(45.6, $workouts[0]['average']);
        $this->assertSame('2026-09-15', $workouts[0]['lastDateKey']);
        $this->assertSame('Tue 15 Sep', $workouts[0]['lastDate']);
        $this->assertSame('HOLLYWOOD', $workouts[1]['displayName']);
        $this->assertSame(1, $workouts[1]['sessions']);
        $this->assertSame(33.0, $workouts[1]['best']);
    }
}
