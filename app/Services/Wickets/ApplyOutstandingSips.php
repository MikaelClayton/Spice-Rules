<?php

namespace App\Services\Wickets;

use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Support\Collection;

class ApplyOutstandingSips
{
    public function handle(WicketGroup $group, User $user, int $sips): int
    {
        return array_sum(array_column($this->breakdown($group, $user, $sips), 'sips'));
    }

    /**
     * Apply sips oldest first and report how many came off each fine.
     *
     * @return list<array{fine_id: int, reason: string, sips: int, sips_owed: int, issued_by: string}>
     */
    public function breakdown(WicketGroup $group, User $user, int $sips): array
    {
        $fines = WicketFine::query()
            ->with('issuedBy:id,name')
            ->whereBelongsTo($group)
            ->where('issued_to_user_id', $user->id)
            ->whereNull('completed_at')
            ->where('sips_owed', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $outstanding = $fines->sum(fn (WicketFine $fine): int => $fine->remainingSips());

        return $this->apply($fines, min($sips, $outstanding));
    }

    /**
     * @param  Collection<int, WicketFine>  $fines
     * @return list<array{fine_id: int, reason: string, sips: int, sips_owed: int, issued_by: string}>
     */
    private function apply(Collection $fines, int $sips): array
    {
        $remaining = $sips;
        $breakdown = [];

        foreach ($fines as $fine) {
            if ($remaining === 0) {
                break;
            }

            $applied = min($fine->remainingSips(), $remaining);
            $fine->sips_completed = $fine->sips_completed + $applied;
            $remaining -= $applied;

            if ($fine->sips_completed >= $fine->sips_owed) {
                $fine->completed_at = now();
            }

            $fine->save();

            $breakdown[] = [
                'fine_id' => $fine->id,
                'reason' => $fine->reason,
                'sips' => $applied,
                'sips_owed' => $fine->sips_owed,
                'issued_by' => $fine->issuedBy?->name ?? 'Someone',
            ];
        }

        return $breakdown;
    }
}
