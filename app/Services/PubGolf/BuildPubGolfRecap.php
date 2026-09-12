<?php

namespace App\Services\PubGolf;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfDrinkLog;
use App\Models\PubGolfParticipant;
use App\Models\User;
use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BuildPubGolfRecap
{
    public function __construct(
        private DescribePubGolfPace $describePubGolfPace,
        private ResolveDisplayTimezone $resolveDisplayTimezone,
    ) {}

    /**
     * @return array{
     *     drink_count: int,
     *     alcoholic_count: int,
     *     units: float,
     *     pace: array{drinks_per_hour: float, label: string, level: int, tone: string, steps: list<string>, duration_seconds: int, duration_label: string},
     *     joined_at: string,
     *     left_at: string,
     *     group_still_going: bool,
     *     rank: int,
     *     field_size: int,
     *     by_drink: list<array{drink: string, label: string, emoji: string, count: int}>,
     *     by_category: list<array{category: string, label: string, emoji: string, count: int}>,
     *     hourly: list<array{label: string, count: int}>,
     *     timeline: list<array{time: string, label: string, emoji: string}>,
     *     standings: list<array{user_id: int, name: string, color: string, alcoholic: int, still_in: bool, is_you: bool}>,
     *     charts: array{hourly: list<array{label: string, count: int}>, categories: list<array{label: string, count: int}>, cumulative: list<array{label: string, count: int}>}
     * }
     */
    public function handle(PubGolfCrawl $crawl, User $viewer): array
    {
        $crawl->load([
            'participants.user',
            'drinkLogs.user',
            'drinkLogs.drink',
        ]);

        $participant = $crawl->participantFor($viewer);

        if ($participant === null) {
            throw new InvalidArgumentException('Viewer did not join this crawl.');
        }

        $endedAt = $participant->left_at ?? $crawl->ended_at ?? now();
        $logs = $crawl->drinkLogs
            ->filter(fn (PubGolfDrinkLog $log): bool => $log->user_id === $viewer->id)
            ->sortBy('id')
            ->values();
        $units = round($logs->sum(fn (PubGolfDrinkLog $log): float => $log->listed()->standardDrinks), 1);
        $pace = $this->describePubGolfPace->snapshot($logs->count(), $participant->durationSeconds($endedAt));
        $hourly = $this->hourly($logs, $participant->joined_at, $endedAt);
        $byCategory = $this->byCategory($logs);
        $standings = $this->standings($crawl, $viewer);

        return [
            'drink_count' => $logs->count(),
            'alcoholic_count' => $logs->count(),
            'units' => $units,
            'pace' => $pace,
            'joined_at' => $participant->joined_at->copy()->timezone($this->resolveDisplayTimezone->name())->format('g:i A'),
            'left_at' => $endedAt->copy()->timezone($this->resolveDisplayTimezone->name())->format('g:i A'),
            'group_still_going' => $crawl->isOpen(),
            'rank' => $this->rank($standings, $viewer),
            'field_size' => count($standings),
            'by_drink' => $this->byDrink($logs),
            'by_category' => $byCategory,
            'hourly' => $hourly,
            'timeline' => $logs
                ->map(function (PubGolfDrinkLog $log): array {
                    $listed = $log->listed();

                    return [
                        'time' => $log->created_at?->copy()->timezone($this->resolveDisplayTimezone->name())->format('H:i') ?? '',
                        'label' => $listed->label,
                        'emoji' => $listed->category->emoji(),
                    ];
                })
                ->all(),
            'standings' => $standings,
            'charts' => [
                'hourly' => $hourly,
                'categories' => array_map(
                    fn (array $row): array => ['label' => $row['label'], 'count' => $row['count']],
                    $byCategory,
                ),
                'cumulative' => $this->cumulative($hourly),
            ],
        ];
    }

    /**
     * @param  Collection<int, PubGolfDrinkLog>  $logs
     * @return list<array{drink: string, label: string, emoji: string, count: int}>
     */
    private function byDrink(Collection $logs): array
    {
        return $logs
            ->countBy(fn (PubGolfDrinkLog $log): int => $log->drink_id)
            ->sortDesc()
            ->map(function (int $count, mixed $drinkId) use ($logs): ?array {
                $log = $logs->firstWhere('drink_id', (int) $drinkId);

                if (! $log instanceof PubGolfDrinkLog) {
                    return null;
                }

                $drink = $log->listed();

                return [
                    'drink' => $drink->key,
                    'label' => $drink->label,
                    'emoji' => $drink->category->emoji(),
                    'count' => $count,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PubGolfDrinkLog>  $logs
     * @return list<array{category: string, label: string, emoji: string, count: int}>
     */
    private function byCategory(Collection $logs): array
    {
        return $logs
            ->countBy(fn (PubGolfDrinkLog $log): string => $log->listed()->category->value)
            ->sortDesc()
            ->map(function (int $count, string $value): ?array {
                $category = PubGolfDrinkCategory::tryFrom($value);

                if ($category === null) {
                    return null;
                }

                return [
                    'category' => $category->value,
                    'label' => $category->label(),
                    'emoji' => $category->emoji(),
                    'count' => $count,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PubGolfDrinkLog>  $logs
     * @return list<array{label: string, count: int}>
     */
    private function hourly(Collection $logs, Carbon $startedAt, Carbon $endedAt): array
    {
        $counts = $logs
            ->countBy(fn (PubGolfDrinkLog $log): string => $log->created_at?->copy()->timezone($this->resolveDisplayTimezone->name())->format('Y-m-d H:00') ?? '');

        $cursor = $startedAt->copy()->timezone($this->resolveDisplayTimezone->name())->startOfHour();
        $end = $endedAt->copy()->timezone($this->resolveDisplayTimezone->name())->startOfHour();
        $hourly = [];

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d H:00');
            $hourly[] = [
                'label' => $cursor->format('j M H:00'),
                'count' => $counts[$key] ?? 0,
            ];
            $cursor->addHour();
        }

        return $hourly;
    }

    /**
     * @param  list<array{label: string, count: int}>  $hourly
     * @return list<array{label: string, count: int}>
     */
    private function cumulative(array $hourly): array
    {
        $running = 0;

        return array_map(function (array $row) use (&$running): array {
            $running += $row['count'];

            return [
                'label' => $row['label'],
                'count' => $running,
            ];
        }, $hourly);
    }

    /**
     * @return list<array{user_id: int, name: string, color: string, alcoholic: int, still_in: bool, is_you: bool}>
     */
    private function standings(PubGolfCrawl $crawl, User $viewer): array
    {
        $counts = $crawl->drinkLogs->countBy(fn (PubGolfDrinkLog $log): int => $log->user_id);

        return $crawl->participants
            ->map(function (PubGolfParticipant $participant) use ($counts, $viewer): array {
                $user = $participant->user;

                return [
                    'user_id' => $participant->user_id,
                    'name' => $user?->name ?? 'Unknown',
                    'color' => $user?->boardColor() ?? User::fallbackColor($participant->user_id),
                    'alcoholic' => $counts[$participant->user_id] ?? 0,
                    'still_in' => $participant->isActive(),
                    'is_you' => $participant->user_id === $viewer->id,
                ];
            })
            ->sortBy([
                ['alcoholic', 'desc'],
                ['name', 'asc'],
                ['user_id', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{user_id: int, name: string, color: string, alcoholic: int, still_in: bool, is_you: bool}>  $standings
     */
    private function rank(array $standings, User $viewer): int
    {
        foreach ($standings as $index => $row) {
            if ($row['user_id'] === $viewer->id) {
                return $index + 1;
            }
        }

        return count($standings);
    }
}
