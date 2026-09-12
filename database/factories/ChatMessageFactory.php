<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\PubGolfCrawl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chatable_type' => (new PubGolfCrawl)->getMorphClass(),
            'chatable_id' => PubGolfCrawl::factory(),
            'user_id' => fn (array $attributes) => PubGolfCrawl::query()
                ->findOrFail($attributes['chatable_id'])
                ->user_id,
            'body' => fake()->sentence(),
            'photo_path' => null,
        ];
    }
}
