<?php

namespace App\Services\Spirdle;

use App\Models\SpirdlePlay;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildSpirdleYou
{
    /**
     * @return array{
     *     hasData: bool,
     *     stats: array{
     *         played: int,
     *         wins: int,
     *         winPercent: int,
     *         currentStreak: int,
     *         longestStreak: int,
     *         fastestMs: int|null,
     *         fastestLabel: string|null,
     *         averageGuesses: float|null
     *     },
     *     distribution: list<array{guesses: int|string, count: int, percent: int}>,
     *     fastest: list<array{date: string, label: string, guesses: int, duration: string|null, won: bool}>
     * }
     */
    public function handle(User $user): array
    {
        $plays = SpirdlePlay::query()
            ->with('puzzle')
            ->whereBelongsTo($user)
            ->finished()
            ->orderBy('id')
            ->get();

        $wins = $plays->filter(fn (SpirdlePlay $play): bool => $play->won);
        $fastest = $wins
            ->filter(fn (SpirdlePlay $play): bool => $play->duration_ms !== null)
            ->sortBy([
                ['duration_ms', 'asc'],
                ['guess_count', 'asc'],
                ['id', 'asc'],
            ])
            ->first();

        return [
            'hasData' => $plays->isNotEmpty(),
            'stats' => [
                'played' => $plays->count(),
                'wins' => $wins->count(),
                'winPercent' => $plays->isEmpty() ? 0 : (int) round(100 * $wins->count() / $plays->count()),
                'currentStreak' => $this->currentStreak($plays),
                'longestStreak' => $this->longestStreak($plays),
                'fastestMs' => $fastest?->duration_ms,
                'fastestLabel' => $fastest?->durationLabel(),
                'averageGuesses' => $wins->isEmpty() ? null : round($wins->avg('guess_count'), 1),
            ],
            'distribution' => $this->distribution($plays),
            'fastest' => $wins
                ->sortBy([
                    ['duration_ms', 'asc'],
                    ['guess_count', 'asc'],
                    ['id', 'asc'],
                ])
                ->take(8)
                ->values()
                ->map(fn (SpirdlePlay $play): array => $this->row($play))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, SpirdlePlay>  $plays
     * @return list<array{guesses: int|string, count: int, percent: int}>
     */
    private function distribution(Collection $plays): array
    {
        $total = max($plays->count(), 1);
        $rows = [];

        foreach (range(1, SpirdlePlay::MAX_GUESSES) as $guesses) {
            $count = $plays
                ->filter(fn (SpirdlePlay $play): bool => $play->won && $play->guess_count === $guesses)
                ->count();

            $rows[] = [
                'guesses' => $guesses,
                'count' => $count,
                'percent' => (int) round(100 * $count / $total),
            ];
        }

        $fails = $plays->filter(fn (SpirdlePlay $play): bool => ! $play->won)->count();
        $rows[] = [
            'guesses' => 'X',
            'count' => $fails,
            'percent' => (int) round(100 * $fails / $total),
        ];

        return $rows;
    }

    /**
     * @param  Collection<int, SpirdlePlay>  $plays
     */
    private function currentStreak(Collection $plays): int
    {
        $dates = $this->winDates($plays);

        if ($dates === []) {
            return 0;
        }

        $cursor = today();

        if (! in_array($cursor->toDateString(), $dates, true)) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;

        while (in_array($cursor->toDateString(), $dates, true)) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * @param  Collection<int, SpirdlePlay>  $plays
     */
    private function longestStreak(Collection $plays): int
    {
        $dates = $this->winDates($plays);

        if ($dates === []) {
            return 0;
        }

        sort($dates);
        $longest = 1;
        $current = 1;

        for ($index = 1; $index < count($dates); $index++) {
            $previous = Carbon::createFromFormat('Y-m-d', $dates[$index - 1])?->startOfDay();
            $day = Carbon::createFromFormat('Y-m-d', $dates[$index])?->startOfDay();

            if ($previous !== null && $day !== null && $previous->copy()->addDay()->equalTo($day)) {
                $current++;
                $longest = max($longest, $current);

                continue;
            }

            $current = 1;
        }

        return $longest;
    }

    /**
     * @param  Collection<int, SpirdlePlay>  $plays
     * @return list<string>
     */
    private function winDates(Collection $plays): array
    {
        return $plays
            ->filter(fn (SpirdlePlay $play): bool => $play->won)
            ->map(fn (SpirdlePlay $play): ?string => $play->puzzle?->play_date?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{date: string, label: string, guesses: int, duration: string|null, won: bool}
     */
    private function row(SpirdlePlay $play): array
    {
        $date = $play->puzzle?->play_date;

        return [
            'date' => $date?->toDateString() ?? '',
            'label' => $date?->format('D j M') ?? '',
            'guesses' => $play->guess_count,
            'duration' => $play->durationLabel(),
            'won' => $play->won,
        ];
    }
}
