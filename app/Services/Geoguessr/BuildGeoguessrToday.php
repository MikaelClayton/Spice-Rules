<?php

namespace App\Services\Geoguessr;

use App\Models\GeoguesserChallenge;
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
     *     revision: string
     * }
     */
    public function handle(): array
    {
        $results = GeoguesserChallenge::query()
            ->with('geoguesser.user')
            ->whereDate('attempted_at', today())
            ->orderByDesc('total_score')
            ->orderBy('updated_at')
            ->get();

        return [
            'results' => $results,
            'ranks' => $this->ranker->ranks($results),
            ...$this->todayAwards($results),
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
