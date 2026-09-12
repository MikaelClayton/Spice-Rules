<?php

namespace Tests\Unit\Services\PubGolf;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfCustomDrink;
use App\Models\PubGolfDrinkLog;
use App\Models\PubGolfParticipant;
use App\Models\User;
use App\Services\PubGolf\BuildPubGolfRecap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildPubGolfRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_recap_reports_duration_drinks_per_hour_and_rank(): void
    {
        $this->travelTo('2026-09-08 18:00:00');
        $host = User::factory()->create(['name' => 'Host']);
        $friend = User::factory()->create(['name' => 'Friend']);
        $crawl = PubGolfCrawl::factory()->create([
            'user_id' => $host->id,
            'started_at' => now(),
        ]);
        $crawl->participants()->where('user_id', $host->id)->update(['joined_at' => now()]);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
            'joined_at' => now(),
        ]);

        $lager = PubGolfCustomDrink::factory()->create([
            'user_id' => $host->id,
            'name' => 'Castle Lager',
            'category' => PubGolfDrinkCategory::Beer,
        ]);
        $savanna = PubGolfCustomDrink::factory()->create([
            'user_id' => $friend->id,
            'name' => 'Savanna Dry',
            'category' => PubGolfDrinkCategory::Cider,
        ]);

        PubGolfDrinkLog::factory()->count(2)->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $host->id,
            'drink_id' => $lager->id,
            'created_at' => now(),
        ]);
        PubGolfDrinkLog::factory()->count(4)->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
            'drink_id' => $savanna->id,
            'created_at' => now(),
        ]);

        $this->travel(2)->hours();
        $crawl->participants()->where('user_id', $host->id)->update(['left_at' => now()]);

        $recap = app(BuildPubGolfRecap::class)->handle($crawl->fresh(['participants.user', 'drinkLogs.user', 'drinkLogs.drink']), $host);

        $this->assertSame(2, $recap['alcoholic_count']);
        $this->assertSame(2.0, $recap['units']);
        $this->assertSame('2h 0m', $recap['pace']['duration_label']);
        $this->assertSame(1.0, $recap['pace']['drinks_per_hour']);
        $this->assertSame('Steady', $recap['pace']['label']);
        $this->assertSame(2, $recap['pace']['level']);
        $this->assertSame('success', $recap['pace']['tone']);
        $this->assertSame(2, $recap['rank']);
        $this->assertSame(2, $recap['field_size']);
        $this->assertTrue($recap['group_still_going']);
        $this->assertSame('8 Sep 18:00', $recap['hourly'][0]['label']);
        $this->assertSame(2, $recap['hourly'][0]['count']);
        $this->assertSame('8 Sep 20:00', $recap['hourly'][2]['label']);
        $this->assertSame(0, $recap['hourly'][2]['count']);
        $this->assertSame(2, $recap['charts']['cumulative'][2]['count']);
        $this->assertSame('Beer', $recap['by_category'][0]['label']);
        $this->assertSame('Castle Lager', $recap['by_drink'][0]['label']);
    }
}
