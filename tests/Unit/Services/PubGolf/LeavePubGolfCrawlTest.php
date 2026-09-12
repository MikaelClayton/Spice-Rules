<?php

namespace Tests\Unit\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use App\Services\PubGolf\LeavePubGolfCrawl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeavePubGolfCrawlTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaving_as_the_last_active_player_wraps_the_crawl(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $participant = app(LeavePubGolfCrawl::class)->handle($crawl, $user);
        $crawl = $crawl->fresh();

        $this->assertNotNull($participant->left_at);
        $this->assertNotNull($crawl->ended_at);
        $this->assertTrue($participant->left_at->eq($crawl->ended_at));
        $this->assertSame(now()->timestamp, $participant->left_at->timestamp);
    }

    public function test_leaving_while_someone_else_is_out_keeps_the_crawl_open(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $host->id]);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
        ]);

        app(LeavePubGolfCrawl::class)->handle($crawl, $host);

        $this->assertNull($crawl->fresh()->ended_at);
        $this->assertTrue($crawl->fresh()->isActiveParticipant($friend));
    }
}
