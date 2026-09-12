<?php

namespace App\Services\Wickets;

use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildWicketBoard
{
    public function __construct(
        private readonly BuildWicketActivity $buildWicketActivity,
        private readonly BuildWicketStandings $buildWicketStandings,
    ) {}

    /**
     * @return array{
     *     activity: Collection<int, array<string, mixed>>,
     *     standings: Collection<int, array<string, mixed>>,
     *     hideOwnFines: bool,
     *     myRemainingSips: int,
     *     mySipFines: Collection<int, WicketFine>,
     *     mySpecials: Collection<int, WicketFine>,
     *     mySpecialCounts: Collection<int, array<string, mixed>>,
     *     revision: string
     * }
     */
    public function handle(WicketGroup $group, User $viewer): array
    {
        $group->load([
            'users' => fn ($query) => $query->orderBy('name')->orderBy('id'),
        ]);

        $hideOwnFines = $group->hidesOwnFinesFrom($viewer);
        $canSeeAllFines = $group->canSeeAllFines($viewer);
        $activity = $this->buildWicketActivity->handle($group);

        if ($hideOwnFines) {
            $activity = $activity
                ->filter(function (array $item) use ($viewer, $canSeeAllFines): bool {
                    if ($item['kind'] === 'drink') {
                        return $canSeeAllFines || $item['log']?->user_id === $viewer->id;
                    }

                    $fine = $item['fine'];

                    if ($fine === null || $fine->issued_to_user_id === $viewer->id) {
                        return false;
                    }

                    return $canSeeAllFines || $fine->issued_by_user_id === $viewer->id;
                })
                ->values();
        }

        $outstanding = $group->fines()
            ->with(['issuedTo', 'issuedBy'])
            ->whereNull('completed_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $standings = $this->buildWicketStandings->handle($group, $viewer, $outstanding);
        $myOutstanding = $outstanding->where('issued_to_user_id', $viewer->id);
        $myRemainingSips = $myOutstanding->sum(fn (WicketFine $fine): int => $fine->remainingSips());
        $mySpecials = $myOutstanding
            ->filter(fn (WicketFine $fine): bool => $fine->type->isSip() === false)
            ->values();
        $mySpecialCounts = $hideOwnFines
            ? $this->buildWicketStandings->hiddenSpecials()
            : $this->buildWicketStandings->countSpecials($mySpecials);

        return [
            'activity' => $activity,
            'standings' => $standings,
            'hideOwnFines' => $hideOwnFines,
            'myRemainingSips' => $myRemainingSips,
            'mySipFines' => $hideOwnFines
                ? collect()
                : $myOutstanding
                    ->filter(fn (WicketFine $fine): bool => $fine->type->isSip())
                    ->values(),
            'mySpecials' => $hideOwnFines ? collect() : $mySpecials,
            'mySpecialCounts' => $mySpecialCounts,
            'revision' => $this->revision($group),
        ];
    }

    public function revision(WicketGroup $group): string
    {
        $fines = $group->fines()
            ->toBase()
            ->selectRaw('count(*) as aggregate_count, max(updated_at) as latest_updated_at')
            ->first();
        $sips = $group->sipLogs()
            ->toBase()
            ->selectRaw('count(*) as aggregate_count, max(updated_at) as latest_updated_at')
            ->first();

        return implode('|', [
            $group->users()->count(),
            $this->formatStamp((int) ($fines->aggregate_count ?? 0), $fines->latest_updated_at ?? null),
            $this->formatStamp((int) ($sips->aggregate_count ?? 0), $sips->latest_updated_at ?? null),
        ]);
    }

    private function formatStamp(int $count, mixed $updatedAt): string
    {
        $stamp = 0;

        if ($updatedAt instanceof CarbonInterface) {
            $stamp = $updatedAt->getTimestamp();
        } elseif (is_string($updatedAt) && $updatedAt !== '') {
            $stamp = Carbon::parse($updatedAt)->getTimestamp();
        }

        return $count.':'.$stamp;
    }
}
