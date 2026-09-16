<?php

namespace App\Services\FitIsh;

use App\Models\FitIshSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildFitIshWeekly
{
    /**
     * @return array{
     *     start: string,
     *     end: string,
     *     label: string,
     *     range: string,
     *     isCurrent: bool,
     *     hasPrevious: bool,
     *     hasNext: bool,
     *     previousStart: string,
     *     nextStart: string,
     *     daysLogged: int,
     *     standings: list<array<string, mixed>>,
     *     days: list<array<string, mixed>>
     * }
     */
    public function payload(?string $week = null): array
    {
        $all = FitIshSession::query()
            ->with(['user', 'studio', 'workout'])
            ->orderBy('class_date')
            ->orderBy('id')
            ->get();

        $currentStart = $this->sundayStart(today());
        $start = $this->sundayStart($this->parseWeek($week));

        if ($start->greaterThan($currentStart)) {
            $start = $currentStart->copy();
        }

        $end = $start->copy()->addDays(6);
        $nextSunday = $start->copy()->addWeek();
        $startKey = $start->toDateString();
        $endKey = $end->toDateString();
        $inWeek = $all
            ->filter(function (FitIshSession $session) use ($startKey, $endKey): bool {
                $date = $session->class_date?->toDateString();

                return $date !== null && $date >= $startKey && $date <= $endKey;
            })
            ->values();

        return [
            'start' => $startKey,
            'end' => $endKey,
            'label' => $this->weekLabel($start, $nextSunday),
            'range' => $start->format('D j M').' – '.$nextSunday->format('D j M Y'),
            'isCurrent' => $start->equalTo($currentStart),
            'hasPrevious' => $all->contains(
                fn (FitIshSession $session): bool => $session->class_date?->toDateString() < $startKey
            ),
            'hasNext' => $start->lessThan($currentStart),
            'previousStart' => $start->copy()->subWeek()->toDateString(),
            'nextStart' => $nextSunday->toDateString(),
            'daysLogged' => $inWeek
                ->map(fn (FitIshSession $session): ?string => $session->class_date?->toDateString())
                ->filter()
                ->unique()
                ->count(),
            'standings' => $this->standings($inWeek),
            'days' => $this->days($start, $inWeek),
        ];
    }

    private function parseWeek(?string $week): Carbon
    {
        if (! is_string($week) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) !== 1) {
            return today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $week)?->startOfDay() ?? today();
        } catch (\Throwable) {
            return today();
        }
    }

    private function sundayStart(Carbon $day): Carbon
    {
        return $day->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
    }

    /**
     * @param  Collection<int, FitIshSession>  $inWeek
     * @return list<array<string, mixed>>
     */
    private function days(Carbon $start, Collection $inWeek): array
    {
        $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $inWeek, $names): array {
                $day = $start->copy()->addDays($offset);
                $date = $day->toDateString();
                $results = $inWeek
                    ->filter(fn (FitIshSession $session): bool => $session->class_date?->toDateString() === $date)
                    ->sortBy([
                        ['points', 'desc'],
                        ['id', 'asc'],
                    ])
                    ->values()
                    ->map(fn (FitIshSession $session): array => $this->result($session))
                    ->all();

                return [
                    'date' => $date,
                    'name' => $names[$offset],
                    'short' => $day->format('D j'),
                    'isToday' => $date === today()->toDateString(),
                    'results' => $results,
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, FitIshSession>  $inWeek
     * @return list<array<string, mixed>>
     */
    private function standings(Collection $inWeek): array
    {
        $rows = $inWeek
            ->groupBy('user_id')
            ->map(function (Collection $sessions): array {
                $user = $sessions->first()?->user;
                $points = $sessions->map(fn (FitIshSession $session): float => $session->pointsValue());
                $best = $sessions->sortByDesc(fn (FitIshSession $session): float => $session->pointsValue())->first();

                return [
                    'userId' => (int) $sessions->first()?->user_id,
                    'label' => $user instanceof User ? $user->name : 'Unknown',
                    'color' => $user instanceof User ? $user->boardColor() : '#283030',
                    'played' => $sessions->count(),
                    'total' => round($points->sum(), 1),
                    'average' => $points->isEmpty() ? 0 : round($points->avg(), 1),
                    'best' => $best?->pointsValue(),
                    'bestDate' => $best?->class_date?->format('D j'),
                    'calories' => (int) $sessions->sum('estimated_calories'),
                ];
            })
            ->sortBy([
                ['total', 'desc'],
                ['played', 'desc'],
                ['label', 'asc'],
            ])
            ->values();

        $place = 1;
        $seen = 0;
        $previous = null;

        return $rows
            ->map(function (array $row) use (&$place, &$seen, &$previous): array {
                $seen++;
                $total = (float) $row['total'];

                if ($previous !== $total) {
                    $place = $seen;
                }

                $row['place'] = $place;
                $previous = $total;

                return $row;
            })
            ->all();
    }

    /**
     * @return array{label: string, color: string, score: float|null, workout: string|null}
     */
    private function result(FitIshSession $session): array
    {
        $user = $session->user;

        return [
            'label' => $user instanceof User ? $user->name : 'Unknown',
            'color' => $user instanceof User ? $user->boardColor() : '#283030',
            'score' => $session->points,
            'workout' => $session->workout?->display_name,
        ];
    }

    private function weekLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameMonth($end)) {
            return $start->format('j').'–'.$end->format('j M');
        }

        return $start->format('j M').' – '.$end->format('j M');
    }
}
