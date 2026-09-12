<?php

namespace Tests\Unit\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use App\Services\PubGolf\RejoinPubGolfCrawl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RejoinPubGolfCrawlTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejoining_clears_the_leave_and_keeps_the_original_join_time(): void
    {
        $this->travelTo('2026-09-10 18:00:00');
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $host->id]);
        $participant = PubGolfParticipant::factory()->left()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
            'joined_at' => now()->subHour(),
        ]);
        $joinedAt = $participant->joined_at?->timestamp;

        $this->travel(30)->minutes();

        $rejoined = app(RejoinPubGolfCrawl::class)->handle($crawl, $friend);

        $this->assertNull($rejoined->left_at);
        $this->assertSame($joinedAt, $rejoined->joined_at?->timestamp);
        $this->assertTrue($crawl->fresh()->isActiveParticipant($friend));
    }

    public function test_rejoining_a_wrapped_crawl_is_rejected(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->ended()->create(['user_id' => $user->id]);

        try {
            app(RejoinPubGolfCrawl::class)->handle($crawl, $user);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That crawl has already wrapped up.'],
                $exception->errors()['crawl'],
            );
        }

        $this->assertNotNull($crawl->fresh()->participantFor($user)?->left_at);
    }
}
