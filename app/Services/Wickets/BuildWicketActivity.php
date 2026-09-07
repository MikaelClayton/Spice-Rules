<?php

namespace App\Services\Wickets;

use App\Enums\WicketFineType;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use App\Models\WicketSipLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildWicketActivity
{
    /**
     * @return Collection<int, array{
     *     kind: 'fine'|'drink'|'special_done',
     *     occurred_at: CarbonInterface,
     *     id: int,
     *     fine: WicketFine|null,
     *     log: WicketSipLog|null
     * }>
     */
    public function handle(WicketGroup $group): Collection
    {
        $fines = $group->fines()
            ->with(['issuedTo', 'issuedBy'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WicketFine $fine): array => [
                'kind' => 'fine',
                'occurred_at' => $fine->created_at,
                'id' => $fine->id,
                'fine' => $fine,
                'log' => null,
            ]);

        $drinks = $group->sipLogs()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WicketSipLog $log): array => [
                'kind' => 'drink',
                'occurred_at' => $log->created_at,
                'id' => $log->id,
                'fine' => null,
                'log' => $log,
            ]);

        $specialsDone = $group->fines()
            ->with(['issuedTo', 'issuedBy'])
            ->whereNotNull('completed_at')
            ->where('type', '!=', WicketFineType::Sips)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WicketFine $fine): array => [
                'kind' => 'special_done',
                'occurred_at' => $fine->completed_at,
                'id' => $fine->id,
                'fine' => $fine,
                'log' => null,
            ]);

        return $fines
            ->concat($drinks)
            ->concat($specialsDone)
            ->sortBy([
                fn (array $left, array $right): int => $right['occurred_at'] <=> $left['occurred_at'],
                fn (array $left, array $right): int => $right['id'] <=> $left['id'],
            ])
            ->values()
            ->take(50);
    }
}
