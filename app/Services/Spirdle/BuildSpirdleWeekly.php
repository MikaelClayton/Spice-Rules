<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\SpirdlePuzzle;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildSpirdleWeekly
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
        $currentStart = $this->sundayStart(today());
        $start = $this->sundayStart($this->parseWeek($week));

        if ($start->greaterThan($currentStart)) {
            $start = $currentStart->copy();
        }

        $end = $start->copy()->addDays(6);
        $nextSunday = $start->copy()->addWeek();
        $startKey = $start->toDateString();
        $endKey = $end->toDateString();
        $inWeek = SpirdlePlay::query()
            ->with(['user', 'puzzle'])
            ->finished()
            ->whereHas(
                'puzzle',
                fn ($query) => $query->whereBetween('play_date', [$startKey, $endKey]),
            )
            ->orderBy('id')
            ->get();

        $hasPrevious = SpirdlePuzzle::query()
            ->where('play_date', '<', $startKey)
            ->whereHas('plays', fn ($query) => $query->finished())
            ->exists();

        return [
            'start' => $startKey,
            'end' => $endKey,
            'label' => $this->weekLabel($start, $nextSunday),
            'range' => $start->format('D j M').' – '.$nextSunday->format('D j M Y'),
            'isCurrent' => $start->equalTo($currentStart),
            'hasPrevious' => $hasPrevious,
            'hasNext' => $start->lessThan($currentStart),
            'previousStart' => $start->copy()->subWeek()->toDateString(),
            'nextStart' => $nextSunday->toDateString(),
            'daysLogged' => $inWeek
                ->map(fn (SpirdlePlay $play): ?string => $play->puzzle?->play_date?->toDateString())
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
     * @param  Collection<int, SpirdlePlay>  $inWeek
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
                    ->filter(fn (SpirdlePlay $play): bool => $play->puzzle?->play_date?->toDateString() === $date)
                    ->sortBy([
                        ['won', 'desc'],
                        ['guess_count', 'asc'],
                        ['duration_ms', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values()
                    ->map(fn (SpirdlePlay $play): array => $this->result($play))
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
     * @param  Collection<int, SpirdlePlay>  $inWeek
     * @return list<array<string, mixed>>
     */
    private function standings(Collection $inWeek): array
    {
        $rows = $inWeek
            ->filter(fn (SpirdlePlay $play): bool => $play->user !== null)
            ->groupBy('user_id')
            ->map(function (Collection $games): array {
                $user = $games->first()?->user;
                $guesses = $games
                    ->filter(fn (SpirdlePlay $play): bool => $play->won)
                    ->pluck('guess_count');
                $fastest = $games
                    ->filter(fn (SpirdlePlay $play): bool => $play->won && $play->duration_ms !== null)
                    ->sortBy('duration_ms')
                    ->first();

                return [
                    'playerId' => (int) $user?->id,
                    'label' => $user instanceof User ? $user->name : 'Unknown',
                    'color' => $user instanceof User ? $user->boardColor() : '#283030',
                    'played' => $games->count(),
                    'wins' => $games->where('won', true)->count(),
                    'total' => $games->sum(fn (SpirdlePlay $play): int => $play->weeklyPoints()),
                    'average' => $guesses->isEmpty() ? null : round($guesses->avg(), 1),
                    'fastest' => $fastest?->durationLabel(),
                    'fastestMs' => $fastest?->duration_ms,
                ];
            })
            ->sortBy([
                ['total', 'desc'],
                ['wins', 'desc'],
                ['played', 'desc'],
                ['average', 'asc'],
                ['fastestMs', 'asc'],
                ['label', 'asc'],
            ])
            ->values();

        return $this->withPlaces($rows);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withPlaces(Collection $rows): array
    {
        $place = 1;
        $seen = 0;
        $previousTotal = null;

        return $rows
            ->map(function (array $row) use (&$place, &$seen, &$previousTotal): array {
                $seen++;
                $total = (int) $row['total'];

                if ($previousTotal !== $total) {
                    $place = $seen;
                }

                $row['place'] = $place;
                $previousTotal = $total;

                return $row;
            })
            ->all();
    }

    /**
     * @return array{label: string, color: string, won: bool, guesses: int, duration: string|null}
     */
    private function result(SpirdlePlay $play): array
    {
        $user = $play->user;

        return [
            'label' => $user instanceof User ? $user->name : 'Unknown',
            'color' => $user instanceof User ? $user->boardColor() : '#283030',
            'won' => $play->won,
            'guesses' => $play->guess_count,
            'duration' => $play->durationLabel(),
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
