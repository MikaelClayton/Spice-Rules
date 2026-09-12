<?php

namespace App\Services\Chat;

use App\Contracts\Chatable;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\User;
use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Database\Eloquent\Model;

class BuildChat
{
    public function __construct(
        private ParseChatMentions $parseChatMentions,
        private ResolveDisplayTimezone $resolveDisplayTimezone,
    ) {}

    /**
     * @return array{
     *     can_send: bool,
     *     unread: int,
     *     mentions: int,
     *     latest_id: int,
     *     mentionable: list<array{id: int, handle: string, name: string, color: string}>,
     *     messages: list<array{id: int, user_id: int, name: string, color: string, body: ?string, photo_url: ?string, is_you: bool, mentioned_you: bool, created_at: string}>
     * }
     */
    public function handle(Model&Chatable $chatable, User $viewer): array
    {
        $participants = $chatable->chatParticipants();
        $messages = ChatMessage::query()
            ->whereMorphedTo('chatable', $chatable)
            ->with(['user', 'mentions'])
            ->orderBy('id')
            ->get();
        $lastReadId = (int) (ChatRead::query()
            ->whereMorphedTo('chatable', $chatable)
            ->whereBelongsTo($viewer)
            ->value('last_read_message_id') ?? 0);
        $unreadMessages = $messages
            ->filter(fn (ChatMessage $message): bool => $message->id > $lastReadId && $message->user_id !== $viewer->id);

        return [
            'can_send' => $chatable->canSendChat($viewer),
            'unread' => $unreadMessages->count(),
            'mentions' => $unreadMessages
                ->filter(fn (ChatMessage $message): bool => $message->mentions->contains('user_id', $viewer->id))
                ->count(),
            'latest_id' => (int) $messages->last()?->id,
            'mentionable' => $this->parseChatMentions->mentionable($participants, $viewer),
            'messages' => $messages
                ->map(function (ChatMessage $message) use ($viewer): array {
                    return [
                        'id' => $message->id,
                        'user_id' => $message->user_id,
                        'name' => $message->user?->name ?? 'Unknown',
                        'color' => $message->user?->boardColor() ?? User::fallbackColor($message->user_id),
                        'body' => $message->body,
                        'photo_url' => $message->photoUrl(),
                        'is_you' => $message->user_id === $viewer->id,
                        'mentioned_you' => $message->mentions->contains('user_id', $viewer->id),
                        'created_at' => $message->created_at?->copy()->timezone($this->resolveDisplayTimezone->name())->format('H:i') ?? '',
                    ];
                })
                ->all(),
        ];
    }
}
