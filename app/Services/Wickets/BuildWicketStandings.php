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
     *     finesHidden: bool
     * }>
     */
    public function handle(WicketGroup $group, User $viewer, Collection $outstanding): Collection
    {
        $fog = $group->hidesOwnFinesFrom($viewer);

        return $group->users
            ->map(function (User $user) use ($outstanding, $viewer, $fog): array {
                $userFines = $outstanding->where('issued_to_user_id', $user->id)->values();
                $hideOwn = $fog && $user->is($viewer);

                if ($fog && ! $hideOwn) {
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

                if ($fog) {
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
            ->sortBy([
                fn (array $left, array $right): int => $this->sortValue($right) <=> $this->sortValue($left),
                fn (array $left, array $right): int => $left['user']->name <=> $right['user']->name,
                fn (array $left, array $right): int => $left['user']->id <=> $right['user']->id,
            ])
            ->values();
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
     * @param  array{sips: int|string, specialCount: int}  $row
     */
    private function sortValue(array $row): int
    {
        $sips = is_int($row['sips']) ? $row['sips'] : 0;

        return $sips * 100 + $row['specialCount'];
    }
}
