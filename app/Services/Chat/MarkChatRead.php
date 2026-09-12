<?php

namespace App\Services\Chat;

use App\Contracts\Chatable;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MarkChatRead
{
    public function handle(Model&Chatable $chatable, User $user, ?int $messageId = null): ChatRead
    {
        $latestId = $messageId ?? ChatMessage::query()
            ->whereMorphedTo('chatable', $chatable)
            ->max('id');

        $latestId = (int) ($latestId ?? 0);

        $read = ChatRead::query()->firstOrNew([
            'chatable_type' => $chatable->getMorphClass(),
            'chatable_id' => $chatable->getKey(),
            'user_id' => $user->id,
        ]);

        $read->last_read_message_id = max((int) $read->last_read_message_id, $latestId);
        $read->save();

        return $read;
    }
}
