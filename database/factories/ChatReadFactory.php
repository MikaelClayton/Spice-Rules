<?php

namespace Database\Factories;

use App\Models\ChatRead;
use App\Models\PubGolfCrawl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatRead>
 */
class ChatReadFactory extends Factory
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
            'last_read_message_id' => 0,
        ];
    }
}
