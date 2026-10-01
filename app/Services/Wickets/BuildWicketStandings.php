<?php

namespace App\Services\Wickets;

use App\Enums\WicketFineType;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Support\Collection;

class BuildWicketStandings
{
    /**
     * @param  Collection<int, WicketFine>  $outstanding
     * @return Collection<int, array{
     *     user: User,
     *     sips: int|string,
     *     sips_at_least: bool,
     *     showSips: bool,
     *     specials: Collection<int, array{type: WicketFineType, count: int|string, at_least: bool}>,
     *     specialCount: int,
     *     fines: Collection<int, WicketFine>,
     *     finesHidden: bool,
     *     severity: array{int, int, int, int},
     *     showAccumulationBreakdown: bool,
     *     rank: int|null
     * }>
     */
    public function handle(WicketGroup $group, User $viewer, Collection $outstanding): Collection
    {
        $hideOwnFines = $group->hidesOwnFinesFrom($viewer);
        $fogOthers = $hideOwnFines && ! $group->canSeeAllFines($viewer);

        return $group->users
            ->map(function (User $user) use ($outstanding, $viewer, $hideOwnFines, $fogOthers): array {
                $userFines = $outstanding->where('issued_to_user_id', $user->id)->values();
                $hideOwn = $hideOwnFines && $user->is($viewer);

                if ($fogOthers && ! $hideOwn) {
                    $userFines = $userFines
                        ->where('issued_by_user_id', $viewer->id)
                        ->values();
                }

                if ($hideOwn) {
                    return [
                        'user' => $user,
                        'sips' => '?',
                        'sips_at_least' => false,
                        'showSips' => true,
                        'specials' => $this->hiddenSpecials(),
                        'specialCount' => 0,
                        'fines' => collect(),
                        'finesHidden' => true,
                    ];
                }

                $specials = $userFines
                    ->filter(fn (WicketFine $fine): bool => $fine->type->isSip() === false)
                    ->values();
                $sips = $userFines->sum(fn (WicketFine $fine): int => $fine->remainingSips());

                if ($fogOthers) {
                    return [
                        'user' => $user,
                        'sips' => $sips > 0 ? $sips : '?',
                        'sips_at_least' => $sips > 0,
                        'showSips' => true,
                        'specials' => $this->fogSpecials($specials),
                        'specialCount' => $specials->count(),
                        'fines' => $userFines,
                        'finesHidden' => false,
                    ];
                }

                return [
                    'user' => $user,
                    'sips' => $sips,
                    'sips_at_least' => false,
                    'showSips' => true,
                    'specials' => $this->countSpecials($specials),
                    'specialCount' => $specials->count(),
                    'fines' => $userFines,
                    'finesHidden' => false,
                ];
            })
            ->map(fn (array $row): array => [
                ...$row,
                'severity' => $this->severity($row),
                'showAccumulationBreakdown' => ! $fogOthers,
            ])
            ->sortBy([
                fn (array $left, array $right): int => $right['severity'] <=> $left['severity'],
                fn (array $left, array $right): int => $left['user']->name <=> $right['user']->name,
                fn (array $left, array $right): int => $left['user']->id <=> $right['user']->id,
            ])
            ->values()
            ->pipe(fn (Collection $rows): Collection => $this->assignRanks($rows));
    }

    /**
     * @param  Collection<int, WicketFine>  $specials
     * @return Collection<int, array{type: WicketFineType, count: int|string, at_least: bool}>
     */
    public function countSpecials(Collection $specials): Collection
    {
        $order = array_flip(array_map(
            fn (WicketFineType $type): string => $type->value,
            WicketFineType::specials(),
        ));

        return $specials
            ->groupBy(fn (WicketFine $fine): string => $fine->type->value)
            ->map(fn (Collection $group): array => [
                'type' => $group->first()->type,
                'count' => $group->count(),
                'at_least' => false,
            ])
            ->sortBy(fn (array $row): int => $order[$row['type']->value] ?? 99)
            ->values();
    }

    /**
     * @return Collection<int, array{type: WicketFineType, count: int|string, at_least: bool}>
     */
    public function hiddenSpecials(): Collection
    {
        return collect(WicketFineType::specials())
            ->map(fn (WicketFineType $type): array => [
                'type' => $type,
                'count' => '?',
                'at_least' => false,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, WicketFine>  $specials
     * @return Collection<int, array{type: WicketFineType, count: int|string, at_least: bool}>
     */
    private function fogSpecials(Collection $specials): Collection
    {
        $counts = $specials->countBy(fn (WicketFine $fine): string => $fine->type->value);

        return collect(WicketFineType::specials())
            ->map(function (WicketFineType $type) use ($counts): array {
                $count = (int) $counts->get($type->value, 0);

                return [
                    'type' => $type,
                    'count' => $count > 0 ? $count : '?',
                    'at_least' => $count > 0,
                ];
            })
            ->values();
    }

    /**
     * Worst first: shoeys, then funnels, then down downs, then sips.
     *
     * @param  array{sips: int|string, fines: Collection<int, WicketFine>}  $row
     * @return array{int, int, int, int}
     */
    private function severity(array $row): array
    {
        $counts = $row['fines']->countBy(fn (WicketFine $fine): string => $fine->type->value);

        return [
            (int) $counts->get(WicketFineType::Shoey->value, 0),
            (int) $counts->get(WicketFineType::Funnel->value, 0),
            (int) $counts->get(WicketFineType::DownDown->value, 0),
            is_int($row['sips']) ? $row['sips'] : 0,
        ];
    }

    /**
     * Tied players share a rank. Players whose fines are hidden are not ranked.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function assignRanks(Collection $rows): Collection
    {
        $position = 0;
        $rank = 0;
        $previous = null;

        return $rows->map(function (array $row) use (&$position, &$rank, &$previous): array {
            if ($row['finesHidden']) {
                return [...$row, 'rank' => null];
            }

            $position++;

            if ($row['severity'] !== $previous) {
                $rank = $position;
                $previous = $row['severity'];
            }

            return [...$row, 'rank' => $rank];
        });
    }
}
