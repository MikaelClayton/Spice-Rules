<?php

namespace Tests\Feature;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfCustomDrink;
use App\Models\PubGolfParticipant;
use App\Models\User;
use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PubGolfCrawlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_pub_golf(): void
    {
        $this->get(route('pub-golf.index'))->assertRedirect(route('login'));
        $this->post(route('pub-golf.store'), ['name' => 'Friday'])->assertRedirect(route('login'));
        $this->post(route('pub-golf.joins.store'), ['code' => 'ABCDEF'])->assertRedirect(route('login'));
        $this->post(route('pub-golf.rejoins.store', 1))->assertRedirect(route('login'));
        $this->assertDatabaseCount('pub_golf_crawls', 0);
    }

    public function test_authenticated_users_see_an_empty_pub_golf_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('pub-golf.index'))
            ->assertOk()
            ->assertSee('Pub Golf')
            ->assertSee('Start a crawl')
            ->assertSee('Join a crawl')
            ->assertSee('Recaps land here after you call it');
    }

    public function test_users_can_start_a_crawl_and_become_the_first_player(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('pub-golf.store'), [
                'name' => 'Friday in Obs',
            ]);

        $crawl = PubGolfCrawl::query()->first();

        $this->assertNotNull($crawl);
        $response->assertRedirect(route('pub-golf.show', $crawl));
        $this->assertDatabaseHas('pub_golf_crawls', [
            'id' => $crawl->id,
            'name' => 'Friday in Obs',
            'user_id' => $user->id,
            'ended_at' => null,
        ]);
        $this->assertSame(6, strlen((string) $crawl->join_code));
        $this->assertDatabaseHas('pub_golf_participants', [
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'left_at' => null,
        ]);
    }

    public function test_a_blank_crawl_name_uses_todays_default(): void
    {
        $this->travelTo('2026-09-08 18:00:00');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('pub-golf.store'), [
                'name' => '   ',
            ]);

        $this->assertDatabaseHas('pub_golf_crawls', [
            'user_id' => $user->id,
            'name' => 'Pub Golf · 8 Sep',
        ]);
    }

    public function test_a_blank_crawl_name_uses_the_browser_timezone_date(): void
    {
        $this->travelTo('2026-09-08 00:30:00');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withUnencryptedCookie(ResolveDisplayTimezone::COOKIE, 'Europe/London')
            ->post(route('pub-golf.store'), [
                'name' => '   ',
            ]);

        $this->assertDatabaseHas('pub_golf_crawls', [
            'user_id' => $user->id,
            'name' => 'Pub Golf · 7 Sep',
        ]);
    }

    public function test_users_cannot_start_a_second_crawl_while_still_out(): void
    {
        $user = User::factory()->create();
        PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.index'))
            ->post(route('pub-golf.store'), [
                'name' => 'Second crawl',
            ])
            ->assertRedirect(route('pub-golf.index'))
            ->assertSessionHasErrors(['name' => 'You are already on a crawl. Call it there before starting another.']);

        $this->assertDatabaseCount('pub_golf_crawls', 1);
    }

    public function test_friends_can_join_an_open_crawl_with_the_code(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create(['name' => 'Sam Fine']);
        $crawl = PubGolfCrawl::factory()->create([
            'user_id' => $host->id,
            'join_code' => 'AB3K7Q',
            'name' => 'Friday in Obs',
        ]);

        $this->actingAs($friend)
            ->post(route('pub-golf.joins.store'), [
                'code' => 'ab3-k7q',
            ])
            ->assertRedirect(route('pub-golf.show', $crawl));

        $this->assertDatabaseHas('pub_golf_participants', [
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
            'left_at' => null,
        ]);

        $this->actingAs($friend)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('Friday in Obs')
            ->assertSee('Sam Fine')
            ->assertSee('No drinks yet. Add one below.')
            ->assertSee('End my crawl')
            ->assertSee('Warming up')
            ->assertSee('Legendary')
            ->assertSee('role="meter"', false);
    }

    public function test_an_unknown_join_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('pub-golf.index'))
            ->post(route('pub-golf.joins.store'), [
                'code' => 'NOPE12',
            ])
            ->assertRedirect(route('pub-golf.index'))
            ->assertSessionHasErrors(['code' => 'No crawl uses that code.']);
    }

    public function test_a_short_join_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('pub-golf.index'))
            ->post(route('pub-golf.joins.store'), [
                'code' => 'AB',
            ])
            ->assertRedirect(route('pub-golf.index'))
            ->assertSessionHasErrors(['code' => 'Crawl codes are 6 characters.']);
    }

    public function test_users_cannot_join_a_crawl_that_has_wrapped_up(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->ended()->create([
            'join_code' => 'ZZZZZZ',
        ]);

        $this->actingAs($user)
            ->from(route('pub-golf.index'))
            ->post(route('pub-golf.joins.store'), [
                'code' => 'ZZZZZZ',
            ])
            ->assertRedirect(route('pub-golf.index'))
            ->assertSessionHasErrors(['code' => 'That crawl has already wrapped up.']);

        $this->assertFalse($crawl->participants()->where('user_id', $user->id)->exists());
    }

    public function test_users_cannot_join_another_crawl_while_still_out(): void
    {
        $host = User::factory()->create();
        $player = User::factory()->create();
        PubGolfCrawl::factory()->create(['user_id' => $player->id]);
        $other = PubGolfCrawl::factory()->create([
            'user_id' => $host->id,
            'join_code' => 'OTHER1',
        ]);

        $this->actingAs($player)
            ->from(route('pub-golf.index'))
            ->post(route('pub-golf.joins.store'), [
                'code' => 'OTHER1',
            ])
            ->assertRedirect(route('pub-golf.index'))
            ->assertSessionHasErrors(['code' => 'You are already on a crawl. Call it there before joining another.']);

        $this->assertFalse($other->participants()->where('user_id', $player->id)->exists());
    }

    public function test_users_who_called_it_can_rejoin_an_open_crawl_with_the_code(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create([
            'user_id' => $host->id,
            'join_code' => 'REJOIN',
        ]);
        $participant = PubGolfParticipant::factory()->left()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
            'joined_at' => now()->subHour(),
        ]);
        $joinedAt = $participant->joined_at?->timestamp;

        $this->actingAs($friend)
            ->from(route('pub-golf.index'))
            ->post(route('pub-golf.joins.store'), [
                'code' => 'REJOIN',
            ])
            ->assertRedirect(route('pub-golf.show', $crawl));

        $participant = $participant->fresh();

        $this->assertNull($participant?->left_at);
        $this->assertSame($joinedAt, $participant?->joined_at?->timestamp);
        $this->assertTrue($crawl->fresh()->isActiveParticipant($friend));
    }

    public function test_people_who_called_it_can_rejoin_from_the_recap_while_the_crawl_is_still_going(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = $this->openCrawl($host, $friend);

        $this->actingAs($host)
            ->post(route('pub-golf.leave.store', $crawl))
            ->assertRedirect(route('pub-golf.recap.show', $crawl));

        $this->actingAs($host)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertOk()
            ->assertSee('Rejoin the crawl')
            ->assertSee('the rest of the crew is still out');

        $this->actingAs($host)
            ->from(route('pub-golf.recap.show', $crawl))
            ->post(route('pub-golf.rejoins.store', $crawl))
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHas('status', 'You are back on the crawl.');

        $this->assertTrue($crawl->fresh()->isActiveParticipant($host));

        $this->actingAs($host)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('End my crawl');
    }

    public function test_a_wrapped_crawl_cannot_be_rejoined(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->ended()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertOk()
            ->assertDontSee('Rejoin the crawl');

        $this->actingAs($user)
            ->from(route('pub-golf.recap.show', $crawl))
            ->post(route('pub-golf.rejoins.store', $crawl))
            ->assertRedirect(route('pub-golf.recap.show', $crawl))
            ->assertSessionHasErrors(['crawl' => 'That crawl has already wrapped up.']);

        $this->assertFalse($crawl->fresh()->isActiveParticipant($user));
        $this->assertFalse($crawl->fresh()->isOpen());
    }

    public function test_users_cannot_rejoin_while_still_out_on_another_crawl(): void
    {
        $host = User::factory()->create();
        $player = User::factory()->create();
        $previous = PubGolfCrawl::factory()->create([
            'user_id' => $host->id,
            'join_code' => 'REJOIN',
        ]);
        PubGolfParticipant::factory()->left()->create([
            'pub_golf_crawl_id' => $previous->id,
            'user_id' => $player->id,
        ]);
        PubGolfCrawl::factory()->create(['user_id' => $player->id]);

        $this->actingAs($player)
            ->from(route('pub-golf.recap.show', $previous))
            ->post(route('pub-golf.rejoins.store', $previous))
            ->assertRedirect(route('pub-golf.recap.show', $previous))
            ->assertSessionHasErrors(['crawl' => 'You are already on a crawl. Call it there before joining another.']);

        $this->assertFalse($previous->fresh()->isActiveParticipant($player));
    }

    public function test_outsiders_cannot_rejoin_a_crawl(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->post(route('pub-golf.rejoins.store', $crawl))
            ->assertRedirect(route('pub-golf.index'));
    }

    public function test_the_starter_leaving_does_not_end_the_crawl_for_everyone_else(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = $this->openCrawl($host, $friend);

        $this->actingAs($host)
            ->post(route('pub-golf.leave.store', $crawl))
            ->assertRedirect(route('pub-golf.recap.show', $crawl))
            ->assertSessionHas('status', 'You called it. The crawl stays open for everyone still out.');

        $crawl->refresh();
        $this->assertNull($crawl->ended_at);
        $this->assertNotNull($crawl->participantFor($host)?->left_at);
        $this->assertTrue($crawl->isActiveParticipant($friend));

        $drink = PubGolfCustomDrink::factory()->create([
            'user_id' => $friend->id,
            'name' => 'Savanna Dry',
            'category' => PubGolfDrinkCategory::Cider,
        ]);

        $this->actingAs($friend)
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $drink->id,
            ])
            ->assertRedirect(route('pub-golf.show', $crawl));

        $this->assertDatabaseHas('pub_golf_drink_logs', [
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
            'drink_id' => $drink->id,
        ]);
    }

    public function test_the_crawl_ends_only_when_the_last_person_calls_it(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = $this->openCrawl($host, $friend);

        $this->actingAs($host)->post(route('pub-golf.leave.store', $crawl));

        $this->actingAs($friend)
            ->post(route('pub-golf.leave.store', $crawl))
            ->assertRedirect(route('pub-golf.recap.show', $crawl))
            ->assertSessionHas('status', 'You were the last one out. The crawl is wrapped.');

        $this->assertNotNull($crawl->fresh()->ended_at);
        $this->assertFalse($crawl->fresh()->isOpen());
    }

    public function test_people_who_called_it_are_sent_to_their_recap(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->ended()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertRedirect(route('pub-golf.recap.show', $crawl));

        $this->actingAs($user)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertOk()
            ->assertSee('You called it')
            ->assertDontSee('Rejoin the crawl')
            ->assertSee('Drinks per hour')
            ->assertSee('What you drank')
            ->assertSee('Per hour')
            ->assertSee('Warming up')
            ->assertSee('Legendary')
            ->assertSee('role="meter"', false);
    }

    public function test_people_still_on_the_crawl_cannot_open_the_recap_yet(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertRedirect(route('pub-golf.show', $crawl));
    }

    public function test_outsiders_are_sent_home_from_a_crawl(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->get(route('pub-golf.show', $crawl))
            ->assertRedirect(route('pub-golf.index'));

        $this->actingAs($outsider)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertRedirect(route('pub-golf.index'));
    }

    public function test_a_missing_crawl_sends_signed_in_users_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pub-golf/999')
            ->assertRedirect(route('pub-golf.index'));
    }

    public function test_past_crawls_are_listed_on_the_home_page(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create([
            'user_id' => $user->id,
            'name' => 'Long Street',
        ]);
        $crawl->participants()->where('user_id', $user->id)->update(['left_at' => now()]);
        $crawl->update(['ended_at' => now()]);

        $this->actingAs($user)
            ->get(route('pub-golf.index'))
            ->assertOk()
            ->assertSee('Long Street')
            ->assertSee('Wrapped');
    }

    private function openCrawl(User $host, User $friend): PubGolfCrawl
    {
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $host->id]);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
        ]);

        return $crawl;
    }
}
