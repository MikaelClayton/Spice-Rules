<?php

namespace App\Services\Push;

use App\Contracts\Chatable;
use App\Models\ChatMessage;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotifyChat
{
    public function __construct(private readonly FcmClient $fcm) {}

    public function handle(Model&Chatable $chatable, User $sender, ChatMessage $message): void
    {
        $recipientIds = $chatable->chatParticipants()
            ->pluck('id')
            ->reject(fn (mixed $id): bool => (int) $id === $sender->id)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $mentionedIds = $message->mentions->pluck('user_id')->map(fn (mixed $id): int => (int) $id);
        $preview = $this->preview($message);
        $url = $chatable->chatUrl();

        $this->sendToUserIds(
            $mentionedIds->intersect($recipientIds),
            [
                'title' => $chatable->chatTitle(),
                'body' => $sender->name.' mentioned you',
                'url' => $url,
            ],
        );

        $this->sendToUserIds(
            $recipientIds->diff($mentionedIds),
            [
                'title' => $chatable->chatTitle(),
                'body' => $preview,
                'url' => $url,
            ],
        );
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

    private function preview(ChatMessage $message): string
    {
        $name = $message->user?->name ?? 'Someone';
        $body = is_string($message->body) ? trim($message->body) : '';

        if ($body !== '') {
            return $name.': '.Str::limit($body, 80);
        }

        return $name.' sent a photo';
    }
}
