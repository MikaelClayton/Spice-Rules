<?php

namespace App\Services\FitIsh;

use App\Models\FitIshProfileSummary;
use App\Models\User;

class PersistFitIshProfileSummary
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(User $user, array $payload): void
    {
        $summary = data_get($payload, 'data.summary');

        if (! is_array($summary)) {
            return;
        }

        foreach ($summary as $key => $row) {
            if (! is_string($key) || ! is_array($row)) {
                continue;
            }

            $timeframe = is_array($row['timeframe'] ?? null) ? $row['timeframe'] : [];

            FitIshProfileSummary::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'timeframe_key' => $key,
                ],
                [
                    'timeframe_id' => is_numeric($timeframe['id'] ?? null) ? (int) $timeframe['id'] : null,
                    'timeframe_name' => is_string($timeframe['name'] ?? null) ? $timeframe['name'] : $key,
                    'number_of_days' => is_numeric($timeframe['numberOfDays'] ?? null) ? (int) $timeframe['numberOfDays'] : null,
                    'session_count' => is_numeric($row['sessionCount'] ?? null) ? (int) $row['sessionCount'] : 0,
                    'average_points' => is_numeric($row['averagePoints'] ?? null) ? $row['averagePoints'] : null,
                    'average_calories' => is_numeric($row['averageCalories'] ?? null) ? (int) $row['averageCalories'] : null,
                    'max_points' => is_numeric($row['maxPoints'] ?? null) ? $row['maxPoints'] : null,
                ],
            );
        }
    }
}
