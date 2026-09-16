<?php

namespace Database\Factories;

use App\Models\FitIshProfileSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FitIshProfileSummary>
 */
class FitIshProfileSummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'timeframe_key' => 'allTime',
            'timeframe_id' => 1,
            'timeframe_name' => 'All Time',
            'number_of_days' => null,
            'session_count' => 1,
            'average_points' => 48.3,
            'average_calories' => 504,
            'max_points' => 48.3,
        ];
    }
}
