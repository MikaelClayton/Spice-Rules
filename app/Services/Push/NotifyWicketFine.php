<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;

class NotifyWicketFine
{
    public function __construct(private readonly FcmClient $fcm) {}

    public function handle(WicketGroup $group, User $issuer, User $target, WicketFine $fine): void
    {
        if ($issuer->is($target)) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $target->id)
            ->orderBy('id')
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $this->fcm->sendToTokens($tokens, [
            'title' => $group->name,
            'body' => $group->isTournament()
                ? "You've been fined 👀"
                : $issuer->name.' fined you '.$fine->displayLabel().'. '.$fine->reason,
            'url' => route('wickets.show', $group),
        ]);
    }
}
