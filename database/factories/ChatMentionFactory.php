<?php

namespace Database\Factories;

use App\Models\ChatMention;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMention>
 */
class ChatMentionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'user_id' => fn (array $attributes) => ChatMessage::query()
                ->findOrFail($attributes['chat_message_id'])
                ->user_id,
        ];
    }
}
