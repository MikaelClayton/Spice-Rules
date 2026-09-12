<?php

namespace Database\Factories;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PubGolfParticipant>
 */
class PubGolfParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pub_golf_crawl_id' => PubGolfCrawl::factory(),
            'user_id' => User::factory(),
            'joined_at' => now(),
            'left_at' => null,
        ];
    }

    public function left(): static
    {
        return $this->state(fn (): array => [
            'left_at' => now(),
        ]);
    }
}
