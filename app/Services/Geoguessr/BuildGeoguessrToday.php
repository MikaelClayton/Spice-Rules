<?php

namespace App\Services\Geoguessr;

use App\Models\GeoguesserChallenge;
use App\Models\GeoguesserRound;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildGeoguessrToday
{
    public function __construct(private readonly RankTodaysChallenges $ranker) {}

    /**
     * @return array{
     *     results: Collection<int, GeoguesserChallenge>,
     *     ranks: array<int, int>,
     *     closestDistance: int|null,
     *     furthestDistance: int|null,
     *     fewestSteps: int|null,
     *     mostSteps: int|null,
     *     overview: array{rounds: list<int>, players: list<array<string, mixed>>},
     *     revision: string
     * }
     */
    public function handle(): array
    {
        $results = GeoguesserChallenge::query()
            ->with(['geoguesser.user', 'rounds'])
            ->whereDate('attempted_at', today())
            ->orderByDesc('total_score')
            ->orderBy('updated_at')
            ->get();

        return [
            'results' => $results,
            'ranks' => $this->ranker->ranks($results),
            ...$this->todayAwards($results),
            'overview' => $this->overview($results),
            'revision' => $this->revision(),
        ];
    }

    public function revision(): string
    {
        $row = GeoguesserChallenge::query()
            ->whereDate('attempted_at', today())
            ->toBase()
            ->selectRaw('count(*) as aggregate_count, max(updated_at) as latest_updated_at')
            ->first();

        return $this->formatRevision(
            (int) ($row->aggregate_count ?? 0),
            $row->latest_updated_at ?? null,
        );
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $results
     * @return array{rounds: list<int>, players: list<array<string, mixed>>}
     */
    private function overview(Collection $results): array
    {
        $roundNumbers = $results
            ->flatMap(fn (GeoguesserChallenge $challenge): Collection => $challenge->rounds->pluck('round_number'))
            ->filter()
            ->map(fn ($number): int => (int) $number)
            ->unique()
            ->sort()
            ->values();

        if ($roundNumbers->isEmpty()) {
            return [
                'rounds' => [1, 2, 3, 4, 5],
                'players' => [],
            ];
        }

        return [
            'rounds' => $roundNumbers->all(),
            'players' => $results
                ->filter(fn (GeoguesserChallenge $challenge): bool => $challenge->geoguesser !== null && $challenge->rounds->isNotEmpty())
                ->map(function (GeoguesserChallenge $challenge) use ($roundNumbers): array {
                    $byRound = $challenge->rounds->keyBy(
                        fn (GeoguesserRound $round): int => (int) $round->round_number,
                    );

                    return [
                        'id' => (int) $challenge->geoguesser_id,
                        'label' => $challenge->geoguesser->displayName(),
                        'color' => $challenge->geoguesser->boardColor(),
                        'scores' => $roundNumbers
                            ->map(function (int $number) use ($byRound): ?int {
                                $score = $byRound->get($number)?->score;

                                return $score === null ? null : (int) $score;
                            })
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $results
     * @return array{closestDistance: int|null, furthestDistance: int|null, fewestSteps: int|null, mostSteps: int|null}
     */
    private function todayAwards(Collection $results): array
    {
        [$closest, $furthest] = $this->minMax($results, 'total_distance');
        [$fewest, $most] = $this->minMax($results, 'total_steps_count');

        return [
            'closestDistance' => $closest,
            'furthestDistance' => $furthest,
            'fewestSteps' => $fewest,
            'mostSteps' => $most,
        ];
    }

    /**
     * @param  Collection<int, GeoguesserChallenge>  $results
     * @return array{0: int|null, 1: int|null}
     */
    private function minMax(Collection $results, string $column): array
    {
        $values = $results
            ->pluck($column)
            ->filter(fn ($value): bool => $value !== null)
            ->map(fn ($value): int => (int) $value);

        $min = $values->min();
        $max = $values->max();

        if ($values->count() < 2 || $min === $max) {
            return [null, null];
        }

        return [$min, $max];
    }

    private function formatRevision(int $count, mixed $updatedAt): string
    {
        $stamp = 0;

        if ($updatedAt instanceof CarbonInterface) {
            $stamp = $updatedAt->getTimestamp();
        } elseif (is_string($updatedAt) && $updatedAt !== '') {
            $stamp = Carbon::parse($updatedAt)->getTimestamp();
        }

        return $count.'|'.$stamp;
    }
}
