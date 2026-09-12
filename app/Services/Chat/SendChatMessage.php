<?php

namespace App\Services\Chat;

use App\Contracts\Chatable;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\PubGolf\StorePubGolfDrinkPhoto;
use App\Services\Push\NotifyChat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendChatMessage
{
    public function __construct(
        private ParseChatMentions $parseChatMentions,
        private StorePubGolfDrinkPhoto $storePubGolfDrinkPhoto,
        private MarkChatRead $markChatRead,
        private NotifyChat $notifyChat,
    ) {}

    public function handle(Model&Chatable $chatable, User $user, ?string $body, ?UploadedFile $photo): ChatMessage
    {
        if (! $chatable->canViewChat($user)) {
            throw ValidationException::withMessages([
                'body' => $chatable->chatDeniedMessage(),
            ]);
        }

        if (! $chatable->canSendChat($user)) {
            throw ValidationException::withMessages([
                'body' => $chatable->chatClosedMessage(),
            ]);
        }

        $body = is_string($body) ? trim($body) : null;
        $body = $body === '' ? null : $body;

        if ($body === null && $photo === null) {
            throw ValidationException::withMessages([
                'body' => 'Write a message or add a photo.',
            ]);
        }

        $photoPath = $photo instanceof UploadedFile
            ? $this->storePubGolfDrinkPhoto->handle($photo, (string) config('chat.photo_directory'))
            : null;

        $message = DB::transaction(function () use ($chatable, $user, $body, $photoPath): ChatMessage {
            $message = new ChatMessage([
                'user_id' => $user->id,
                'body' => $body,
                'photo_path' => $photoPath,
            ]);
            $message->chatable()->associate($chatable);
            $message->save();

            $mentioned = $this->parseChatMentions
                ->handle($body, $chatable->chatParticipants())
                ->reject(fn (User $mentioned): bool => $mentioned->is($user));

            if ($mentioned->isNotEmpty()) {
                $message->mentions()->createMany(
                    $mentioned
                        ->map(fn (User $mentioned): array => ['user_id' => $mentioned->id])
                        ->all(),
                );
            }

            $this->markChatRead->handle($chatable, $user, $message->id);

            return $message->load(['user', 'mentions']);
        });

        $this->notifyChat->handle($chatable, $user, $message);

        return $message;
    }
}
