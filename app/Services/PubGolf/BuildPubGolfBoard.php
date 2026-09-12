<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfDrinkLog;
use App\Models\PubGolfParticipant;
use App\Models\User;
use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Support\Collection;

class BuildPubGolfBoard
{
    public function __construct(
        private DescribePubGolfPace $describePubGolfPace,
        private ResolveDisplayTimezone $resolveDisplayTimezone,
    ) {}

    /**
     * @return array{
     *     my_alcoholic: int,
     *     my_total: int,
     *     pace: array{drinks_per_hour: float, label: string, level: int, tone: string, steps: list<string>, duration_seconds: int, duration_label: string},
     *     joined_at_iso: ?string,
     *     last_drink: ?PubGolfDrinkLog,
     *     last_listed: ?PubGolfListedDrink,
     *     favorites: list<PubGolfListedDrink>,
     *     active_count: int,
     *     left_count: int,
     *     standings: list<array{user_id: int, name: string, color: string, alcoholic: int, still_in: bool, is_you: bool}>,
     *     activity: list<array{id: int, name: string, drink: string, label: string, emoji: string, is_you: bool, created_at: string}>
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
        $logs = $crawl->drinkLogs->sortBy('id')->values();
        $mine = $logs->where('user_id', $viewer->id)->values();
        $duration = $participant?->durationSeconds() ?? 0;
        $last = $mine->last();

        return [
            'my_alcoholic' => $mine->count(),
            'my_total' => $mine->count(),
            'pace' => $this->describePubGolfPace->snapshot($mine->count(), $duration),
            'joined_at_iso' => $participant?->joined_at?->toIso8601String(),
            'last_drink' => $last,
            'last_listed' => $last?->listed(),
            'favorites' => $this->favorites($mine),
            'active_count' => $crawl->participants->filter(fn (PubGolfParticipant $row): bool => $row->isActive())->count(),
            'left_count' => $crawl->participants->filter(fn (PubGolfParticipant $row): bool => ! $row->isActive())->count(),
            'standings' => $this->standings($crawl, $logs, $viewer),
            'activity' => $this->activity($logs->reverse()->take(20)->values(), $viewer),
        ];
    }

    /**
     * @param  Collection<int, PubGolfDrinkLog>  $mine
     * @return list<PubGolfListedDrink>
     */
    private function favorites(Collection $mine): array
    {
        return $mine
            ->countBy(fn (PubGolfDrinkLog $log): int => $log->drink_id)
            ->sortDesc()
            ->keys()
            ->map(function (mixed $drinkId) use ($mine): ?PubGolfListedDrink {
                $log = $mine->firstWhere('drink_id', (int) $drinkId);

                if (! $log instanceof PubGolfDrinkLog) {
                    return null;
                }

                $drink = $log->listed();

                return $drink->isListed ? $drink : null;
            })
            ->filter()
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PubGolfDrinkLog>  $logs
     * @return list<array{user_id: int, name: string, color: string, alcoholic: int, still_in: bool, is_you: bool}>
     */
    private function standings(PubGolfCrawl $crawl, Collection $logs, User $viewer): array
    {
        $counts = $logs->countBy(fn (PubGolfDrinkLog $log): int => $log->user_id);

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
     * @param  Collection<int, PubGolfDrinkLog>  $logs
     * @return list<array{id: int, name: string, drink: string, label: string, emoji: string, is_you: bool, created_at: string}>
     */
    private function activity(Collection $logs, User $viewer): array
    {
        return $logs
            ->map(function (PubGolfDrinkLog $log) use ($viewer): array {
                $listed = $log->listed();

                return [
                    'id' => $log->id,
                    'name' => $log->user?->name ?? 'Unknown',
                    'drink' => $listed->key,
                    'label' => $listed->label,
                    'emoji' => $listed->category->emoji(),
                    'is_you' => $log->user_id === $viewer->id,
                    'created_at' => $log->created_at?->copy()->timezone($this->resolveDisplayTimezone->name())->format('H:i') ?? '',
                ];
            })
            ->all();
    }
}
