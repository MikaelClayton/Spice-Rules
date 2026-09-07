<?php

namespace App\Services\Wickets;

use App\Enums\WicketFineType;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Support\Facades\DB;

class IssueWicketFine
{
    public function __construct(private ConvertSipsToDownDowns $convertSipsToDownDowns) {}

    /**
     * @return array{fine: WicketFine, conversions: int}
     */
    public function handle(
        WicketGroup $group,
        User $issuer,
        User $target,
        WicketFineType $type,
        string $reason,
        int $sips = 0,
    ): array {
        return DB::transaction(function () use ($group, $issuer, $target, $type, $reason, $sips): array {
            $fine = WicketFine::query()->create([
                'wicket_group_id' => $group->id,
                'issued_by_user_id' => $issuer->id,
                'issued_to_user_id' => $target->id,
                'reason' => $reason,
                'type' => $type,
                'sips_owed' => $type->isSip() ? $sips : 0,
            ]);

            $conversions = $type->isSip()
                ? $this->convertSipsToDownDowns->handle($group, $target, $issuer)
                : 0;

            return [
                'fine' => $fine,
                'conversions' => $conversions,
            ];
        });
    }
}
