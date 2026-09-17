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

    /**
     * @param  list<array{target: User, fine: WicketFine}>  $issued
     */
    public function afterResponse(WicketGroup $group, User $issuer, array $issued): void
    {
        if ($issued === []) {
            return;
        }

        defer(static function (): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }, 'push-flush-response');

        $groupId = $group->id;
        $issuerId = $issuer->id;
        $issuedIds = array_map(
            fn (array $item): array => [
                'target_id' => $item['target']->id,
                'fine_id' => $item['fine']->id,
            ],
            $issued,
        );

        defer(function () use ($groupId, $issuerId, $issuedIds): void {
            $group = WicketGroup::query()->find($groupId);
            $issuer = User::query()->find($issuerId);

            if ($group === null || $issuer === null) {
                return;
            }

            foreach ($issuedIds as $item) {
                $target = User::query()->find($item['target_id']);
                $fine = WicketFine::query()->find($item['fine_id']);

                if ($target === null || $fine === null) {
                    continue;
                }

                $this->handle($group, $issuer, $target, $fine);
            }
        }, 'wickets-notify-fines-'.$groupId);
    }

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
