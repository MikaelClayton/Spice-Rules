<?php

namespace Tests\Feature;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfDrinkLog;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndStalePubGolfCrawlsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ends_crawls_with_no_interaction_for_four_hours(): void
    {
        $this->travelTo('2026-09-10 12:00:00');
        $stale = PubGolfCrawl::factory()->create(['started_at' => now()]);
        $host = $stale->starter;
        $friend = User::factory()->create();
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $stale->id,
            'user_id' => $friend->id,
            'joined_at' => now(),
        ]);

        $this->travel(4)->hours();

        $this->artisan('pub-golf:end-stale')
            ->expectsOutput('Ended 1 stale crawl.')
            ->assertSuccessful();

        $this->assertDatabaseHas('cron_runs', [
            'command' => 'pub-golf:end-stale',
            'status' => 'success',
        ]);

        $stale = $stale->fresh();

        $this->assertNotNull($stale?->ended_at);
        $this->assertSame(now()->timestamp, $stale->ended_at->timestamp);
        $this->assertFalse($stale->isActiveParticipant($host));
        $this->assertFalse($stale->isActiveParticipant($friend));
        $this->assertSame(
            now()->timestamp,
            $stale->participantFor($host)?->left_at?->timestamp,
        );
    }

    public function test_it_keeps_crawls_that_still_have_recent_activity(): void
    {
        $this->travelTo('2026-09-10 12:00:00');
        $fresh = PubGolfCrawl::factory()->create(['started_at' => now()]);
        $withRecentDrink = PubGolfCrawl::factory()->create(['started_at' => now()->subHours(5)]);
        $withRecentJoin = PubGolfCrawl::factory()->create(['started_at' => now()->subHours(5)]);
        $alreadyEnded = PubGolfCrawl::factory()->ended()->create(['started_at' => now()->subHours(5)]);
        $endedAt = $alreadyEnded->ended_at;

        PubGolfDrinkLog::factory()->create([
            'pub_golf_crawl_id' => $withRecentDrink->id,
            'user_id' => $withRecentDrink->user_id,
            'created_at' => now()->subHour(),
        ]);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $withRecentJoin->id,
            'user_id' => User::factory(),
            'joined_at' => now()->subHour(),
        ]);

        $this->artisan('pub-golf:end-stale')
            ->expectsOutput('Ended 0 stale crawls.')
            ->assertSuccessful();

        $this->assertDatabaseHas('cron_runs', [
            'command' => 'pub-golf:end-stale',
            'status' => 'success',
        ]);

        $this->assertNull($fresh->fresh()->ended_at);
        $this->assertNull($withRecentDrink->fresh()->ended_at);
        $this->assertNull($withRecentJoin->fresh()->ended_at);
        $this->assertTrue($alreadyEnded->fresh()->ended_at->eq($endedAt));
    }
}
