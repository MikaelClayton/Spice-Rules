<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Support\Collection;

class NotifyWicketFine
{
    public function __construct(private readonly FcmClient $fcm) {}

    public function handle(WicketGroup $group, User $issuer, User $target, WicketFine $fine): void
    {
        $this->notifyTarget($group, $issuer, $target, $fine);

        if ($group->notifiesAllOnFine()) {
            $this->notifySpectators($group, $issuer, $target, $fine);
        }
    }

    private function notifyTarget(WicketGroup $group, User $issuer, User $target, WicketFine $fine): void
    {
        if ($issuer->is($target)) {
            return;
        }

        $this->sendToUserIds([$target->id], [
            'title' => $group->name,
            'body' => $group->isTournament()
                ? "You've been fined 👀"
                : $issuer->name.' fined you '.$fine->displayLabel().'. '.$fine->displayReason(),
            'url' => route('wickets.show', $group),
        ]);
    }

    private function notifySpectators(WicketGroup $group, User $issuer, User $target, WicketFine $fine): void
    {
        $spectatorIds = $group->users()
            ->whereKeyNot([$issuer->id, $target->id])
            ->pluck('users.id');

        $this->sendToUserIds($spectatorIds, [
            'title' => $group->name,
            'body' => $issuer->name.' fined '.$target->name.' '.$fine->displayLabel().' for '.$fine->displayReason(),
            'url' => route('wickets.show', $group),
        ]);
    }

    /**
     * @param  iterable<int, mixed>  $userIds
     * @param  array{title: string, body: string, url: string}  $notification
     */
    private function sendToUserIds(iterable $userIds, array $notification): void
    {
        $ids = Collection::make($userIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $tokens = DeviceToken::query()
            ->whereIn('user_id', $ids)
            ->orderBy('id')
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $this->fcm->sendToTokens($tokens, $notification);
    }
}
