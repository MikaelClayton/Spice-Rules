<?php

namespace Database\Factories;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PubGolfCrawl>
 */
class PubGolfCrawlFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Pub Golf · '.fake()->word(),
            'join_code' => strtoupper(fake()->unique()->bothify('??????')),
            'started_at' => now(),
            'ended_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'ended_at' => now(),
        ])->afterCreating(function (PubGolfCrawl $crawl): void {
            $crawl->participants()
                ->where('user_id', $crawl->user_id)
                ->update(['left_at' => $crawl->ended_at]);
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PubGolfCrawl $crawl): void {
            PubGolfParticipant::query()->firstOrCreate(
                [
                    'pub_golf_crawl_id' => $crawl->id,
                    'user_id' => $crawl->user_id,
                ],
                [
                    'joined_at' => $crawl->started_at ?? now(),
                ],
            );
        });
    }
}
