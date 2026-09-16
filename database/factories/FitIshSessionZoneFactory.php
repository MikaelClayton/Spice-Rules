<?php

namespace Database\Factories;

use App\Models\FitIshSession;
use App\Models\FitIshSessionZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FitIshSessionZone>
 */
class FitIshSessionZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fit_ish_session_id' => FitIshSession::factory(),
            'zone_number' => 1,
            'name' => 'Very light / Recovery',
            'description' => 'Ideal for warm-ups, cool-downs, and active recovery.',
            'color_hex' => '#326EC8',
            'min_percentage' => 0,
            'max_percentage' => 60,
            'min_bpm' => 0,
            'max_bpm' => 145,
            'bpm_label' => '<145 BPM',
            'duration_seconds' => 1536,
            'duration_label' => '25:36',
            'percentage_value' => 62.3,
            'percentage_label' => '62%',
        ];
    }
}
