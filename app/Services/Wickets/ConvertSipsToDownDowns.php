<?php

namespace App\Services\Wickets;

use App\Enums\WicketFineType;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;

class ConvertSipsToDownDowns
{
    public function __construct(private ApplyOutstandingSips $applyOutstandingSips) {}

    public function handle(WicketGroup $group, User $target, User $issuedBy): int
    {
        $converted = 0;

        while ($this->outstandingSips($group, $target) >= WicketFineType::DOWN_DOWN_AT) {
            $this->applyOutstandingSips->handle($group, $target, WicketFineType::DOWN_DOWN_AT);

            WicketFine::query()->create([
                'wicket_group_id' => $group->id,
                'issued_by_user_id' => $issuedBy->id,
                'issued_to_user_id' => $target->id,
                'reason' => '8 sips',
                'type' => WicketFineType::DownDown,
                'sips_owed' => 0,
            ]);

            $converted++;
        }

        return $converted;
    }

    private function outstandingSips(WicketGroup $group, User $user): int
    {
        return (int) WicketFine::query()
            ->whereBelongsTo($group)
            ->where('issued_to_user_id', $user->id)
            ->whereNull('completed_at')
            ->where('sips_owed', '>', 0)
            ->lockForUpdate()
            ->get()
            ->sum(fn (WicketFine $fine): int => $fine->remainingSips());
    }
}
