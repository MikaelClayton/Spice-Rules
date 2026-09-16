<?php

namespace Database\Factories;

use App\Models\FitIshSession;
use App\Models\FitIshStudio;
use App\Models\FitIshWorkout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FitIshSession>
 */
class FitIshSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = today()->toDateString();
        $studio = FitIshStudio::factory();

        return [
            'session_id' => $date.'_0600:studio:'.fake()->unique()->regexify('[a-z0-9]{4}').':serial:'.fake()->numerify('####'),
            'user_id' => User::factory(),
            'fit_ish_studio_id' => $studio,
            'fit_ish_workout_id' => FitIshWorkout::factory(),
            'class_date' => $date,
            'class_time' => '06:00:00',
            'started_at' => today()->setTime(6, 0),
            'timezone' => 'Africa/Johannesburg',
            'localized_date_time' => today()->format('D, M j, Y').' | 6:00am',
            'duration_in_minutes' => 45,
            'tracked_duration_seconds' => 2465,
            'points' => 48.3,
            'average_heartrate' => 133,
            'max_heartrate' => 173,
            'estimated_calories' => 504,
            'heartrate_method' => 'karvonen',
            'max_hr_default' => 190,
            'max_hr_override' => 190,
            'max_hr_value' => 190,
            'resting_hr_default' => 75,
            'resting_hr_override' => 76,
            'resting_hr_value' => 76,
            'graph_type' => 'bpmCandlestick',
        ];
    }
}
