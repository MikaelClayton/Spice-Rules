<?php

namespace App\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\FitIshWorkout;
use Illuminate\Support\Carbon;

class BuildFitIshWorkouts
{
    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     displayName: string,
     *     type: string|null,
     *     description: string|null,
     *     logoUrl: string|null,
     *     sessions: int,
     *     people: int,
     *     best: float|null,
     *     average: float|null,
     *     lastDate: string|null,
     *     lastDateKey: string|null
     * }>
     */
    public function handle(): array
    {
        $stats = FitIshSession::query()
            ->toBase()
            ->select('fit_ish_workout_id')
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw('COUNT(DISTINCT user_id) as people_count')
            ->selectRaw('MAX(points) as best_points')
            ->selectRaw('AVG(points) as average_points')
            ->selectRaw('MAX(class_date) as last_class_date')
            ->whereNotNull('fit_ish_workout_id')
            ->groupBy('fit_ish_workout_id')
            ->get()
            ->keyBy('fit_ish_workout_id');

        return FitIshWorkout::query()
            ->orderBy('display_name')
            ->orderBy('id')
            ->get()
            ->map(function (FitIshWorkout $workout) use ($stats): array {
                $row = $stats->get($workout->id);

                return [
                    'id' => $workout->id,
                    'name' => $workout->name,
                    'displayName' => $workout->display_name,
                    'type' => $workout->type,
                    'description' => $workout->description,
                    'logoUrl' => $workout->logoUrl(),
                    'sessions' => (int) ($row->sessions_count ?? 0),
                    'people' => (int) ($row->people_count ?? 0),
                    'best' => $this->decimal($row->best_points ?? null),
                    'average' => $this->decimal($row->average_points ?? null),
                    'lastDate' => $this->lastDate($row->last_class_date ?? null),
                    'lastDateKey' => $this->dateKey($row->last_class_date ?? null),
                ];
            })
            ->sortBy([
                ['sessions', 'desc'],
                ['displayName', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 1);
    }

    private function lastDate(mixed $value): ?string
    {
        $key = $this->dateKey($value);

        if ($key === null) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $key)?->format('D j M') ?? $key;
    }

    private function dateKey(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return substr((string) $value, 0, 10) ?: null;
    }
}
