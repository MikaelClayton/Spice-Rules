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
        $fines = WicketFine::query()
            ->whereBelongsTo($group)
            ->where('issued_to_user_id', $user->id)
            ->whereNull('completed_at')
            ->where('sips_owed', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $outstanding = $fines->sum(fn (WicketFine $fine): int => $fine->remainingSips());
        $remaining = min($sips, $outstanding);

        $this->apply($fines, $remaining);

        return $remaining;
    }

    /**
     * @param  Collection<int, WicketFine>  $fines
     */
    private function apply(Collection $fines, int $sips): void
    {
        $remaining = $sips;

        foreach ($fines as $fine) {
            if ($remaining === 0) {
                break;
            }

            $needed = $fine->remainingSips();
            $applied = min($needed, $remaining);
            $fine->sips_completed = $fine->sips_completed + $applied;
            $remaining -= $applied;

            if ($fine->sips_completed >= $fine->sips_owed) {
                $fine->completed_at = now();
            }

            $fine->save();
        }
    }
}
