<?php

namespace App\Services\FitIsh;

use App\Models\FitIshSession;
use Illuminate\Support\Collection;

class BuildFitIshToday
{
    /**
     * @return array{
     *     date: string,
     *     isToday: bool,
     *     sessions: Collection<int, FitIshSession>,
     *     ranks: array<int, int>,
     *     mostCalories: int|null,
     *     highestAverageHr: int|null,
     *     mostHardSeconds: int|null
     * }
     */
    public function handle(?string $date = null): array
    {
        $day = $this->day($date);
        $sessions = FitIshSession::query()
            ->with(['user', 'studio', 'workout', 'zones', 'graphPoints'])
            ->whereDate('class_date', $day)
            ->orderByDesc('points')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        return [
            'date' => $day,
            'isToday' => $day === today()->toDateString(),
            'sessions' => $sessions,
            'ranks' => $this->ranks($sessions),
            ...$this->awards($sessions),
        ];
    }

    public function day(?string $date): string
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        return today()->toDateString();
    }

    /**
     * @param  Collection<int, FitIshSession>  $sessions
     * @return array<int, int>
     */
    public function ranks(Collection $sessions): array
    {
        $place = 1;
        $seen = 0;
        $previous = null;
        $ranks = [];

        foreach ($sessions as $session) {
            $seen++;
            $points = $session->pointsValue();

            if ($previous !== $points) {
                $place = $seen;
            }

            $ranks[(int) $session->id] = $place;
            $previous = $points;
        }

        return $ranks;
    }

    /**
     * @param  Collection<int, FitIshSession>  $sessions
     * @return array{mostCalories: int|null, highestAverageHr: int|null, mostHardSeconds: int|null}
     */
    private function awards(Collection $sessions): array
    {
        return [
            'mostCalories' => $this->uniqueExtreme($sessions, fn (FitIshSession $session): ?int => $session->estimated_calories),
            'highestAverageHr' => $this->uniqueExtreme($sessions, fn (FitIshSession $session): ?int => $session->average_heartrate),
            'mostHardSeconds' => $this->uniqueExtreme($sessions, fn (FitIshSession $session): ?int => $session->hardZoneSeconds() ?: null),
        ];
    }

    /**
     * @param  Collection<int, FitIshSession>  $sessions
     * @param  callable(FitIshSession): ?int  $value
     */
    private function uniqueExtreme(Collection $sessions, callable $value): ?int
    {
        $values = $sessions
            ->map($value)
            ->filter(fn ($item): bool => $item !== null)
            ->map(fn ($item): int => (int) $item);

        $max = $values->max();

        if ($values->count() < 2 || $max === $values->min()) {
            return null;
        }

        return is_numeric($max) ? (int) $max : null;
    }
}
