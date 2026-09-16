<?php

namespace Database\Factories;

use App\Models\FitIshSession;
use App\Models\FitIshSessionGraphPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FitIshSessionGraphPoint>
 */
class FitIshSessionGraphPointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fit_ish_session_id' => FitIshSession::factory(),
            'minute' => 4,
            'type' => 'recordedBpm',
            'bpm_min' => 100,
            'bpm_max' => 119,
        ];
    }

    public function withoutRecording(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'noData',
            'bpm_min' => null,
            'bpm_max' => null,
        ]);
    }
}
