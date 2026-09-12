<?php

namespace App\Services\Geoguessr;

use App\Models\Geoguesser;
use App\Models\GeoguesserChallenge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildGeoguessrWeekly
{
    /**
     * @param  Collection<int, GeoguesserChallenge>  $challenges
     * @return array{
     *     start: string,
     *     end: string,
     *     nextSunday: string,
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
    public function payload(Collection $challenges, ?string $week = null): array
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
        $inWeek = $challenges
            ->filter(function (GeoguesserChallenge $challenge) use ($startKey, $endKey): bool {
                $date = $challenge->attempted_at?->toDateString();

                return $date !== null && $date >= $startKey && $date <= $endKey;
            })
            ->values();

        $days = $this->days($start, $inWeek);
        $standings = $this->standings($inWeek);

        return [
            'start' => $startKey,
            'end' => $endKey,
            'nextSunday' => $nextSunday->toDateString(),
            'label' => $this->weekLabel($start, $nextSunday),
            'range' => $start->format('D j M').' – '.$nextSunday->format('D j M Y'),
            'isCurrent' => $start->equalTo($currentStart),
            'hasPrevious' => $challenges->contains(
                fn (GeoguesserChallenge $challenge): bool => $challenge->attempted_at?->toDateString() < $startKey
            ),
            'hasNext' => $start->lessThan($currentStart),
            'previousStart' => $start->copy()->subWeek()->toDateString(),
            'nextStart' => $nextSunday->toDateString(),
            'daysLogged' => $inWeek
                ->map(fn (GeoguesserChallenge $challenge): ?string => $challenge->attempted_at?->toDateString())
                ->filter()
                ->unique()
                ->count(),
            'standings' => $standings,
            'days' => $days,
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
     * @param  Collection<int, GeoguesserChallenge>  $inWeek
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
                    ->filter(fn (GeoguesserChallenge $challenge): bool => $challenge->attempted_at?->toDateString() === $date)
                    ->sortBy([
                        ['total_score', 'desc'],
                        ['id', 'asc'],
                    ])
                    ->values()
                    ->map(fn (GeoguesserChallenge $challenge): array => $this->result($challenge))
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
     * @param  Collection<int, GeoguesserChallenge>  $inWeek
     * @return list<array<string, mixed>>
     */
    private function standings(Collection $inWeek): array
    {
        $grouped = $inWeek
            ->filter(fn (GeoguesserChallenge $challenge): bool => $challenge->geoguesser !== null)
            ->groupBy('geoguesser_id');

        $rows = $grouped
            ->map(function (Collection $games): array {
                $player = $games->first()?->geoguesser;
                $scores = $games
                    ->pluck('total_score')
                    ->filter(fn ($score): bool => $score !== null)
                    ->map(fn ($score): int => (int) $score)
                    ->values();
                $best = $games
                    ->filter(fn (GeoguesserChallenge $challenge): bool => $challenge->total_score !== null)
                    ->sortByDesc('total_score')
                    ->first();

                return [
                    'playerId' => (int) $player?->id,
                    'label' => $player instanceof Geoguesser ? $player->displayName() : 'Unknown',
                    'color' => $player instanceof Geoguesser ? $player->boardColor() : '#283030',
                    'played' => $games->count(),
                    'total' => $scores->sum(),
                    'average' => $scores->isEmpty() ? 0 : (int) round($scores->avg()),
                    'best' => $best?->total_score,
                    'bestDate' => $best?->attempted_at?->format('D j'),
                ];
            })
            ->sortBy([
                ['total', 'desc'],
                ['played', 'desc'],
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
     * @return array{label: string, color: string, score: int|null, team: bool}
     */
    private function result(GeoguesserChallenge $challenge): array
    {
        $player = $challenge->geoguesser;

        return [
            'label' => $player instanceof Geoguesser ? $player->displayName() : 'Unknown',
            'color' => $player instanceof Geoguesser ? $player->boardColor() : '#283030',
            'score' => $challenge->total_score,
            'team' => (bool) $challenge->is_done_as_team,
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
