<?php

namespace Tests\Feature;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfCustomDrink;
use App\Models\PubGolfDrinkLog;
use App\Models\PubGolfParticipant;
use App\Models\User;
use App\Services\Timezone\ResolveDisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PubGolfDrinkLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_can_log_a_drink_on_an_open_crawl(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);
        $drink = PubGolfCustomDrink::factory()->create([
            'user_id' => $user->id,
            'name' => 'Black Label',
            'category' => PubGolfDrinkCategory::Beer,
        ]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $drink->id,
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHas('status', 'Black Label logged.');

        $this->assertDatabaseHas('pub_golf_drink_logs', [
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'drink_id' => $drink->id,
        ]);

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('Same again')
            ->assertSee('Black Label');
    }

    public function test_logged_drink_times_use_south_african_standard_time(): void
    {
        $this->travelTo('2026-09-10 15:18:00');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);
        $drink = PubGolfCustomDrink::factory()->create([
            'user_id' => $user->id,
            'name' => 'Castle Lager',
        ]);

        $this->actingAs($user)
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $drink->id,
            ]);

        $this->assertDatabaseHas('pub_golf_drink_logs', [
            'user_id' => $user->id,
            'drink_id' => $drink->id,
            'created_at' => '2026-09-10 15:18:00',
        ]);

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('15:18');
    }

    public function test_logged_drink_times_follow_the_browser_timezone(): void
    {
        $this->travelTo('2026-09-10 15:18:00');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);
        $drink = PubGolfCustomDrink::factory()->create([
            'user_id' => $user->id,
            'name' => 'Castle Lager',
        ]);

        $this->actingAs($user)
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $drink->id,
            ]);

        $this->actingAs($user)
            ->withUnencryptedCookie(ResolveDisplayTimezone::COOKIE, 'Europe/London')
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('14:18')
            ->assertDontSee('>15:18<', false);
    }

    public function test_an_unknown_drink_is_rejected(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => 'absinthe_fountain',
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['drink' => 'Pick a drink from the list.']);

        $this->assertDatabaseCount('pub_golf_drink_logs', 0);
    }

    public function test_outsiders_cannot_log_drinks(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => PubGolfCustomDrink::factory()->create(['user_id' => $owner->id])->id,
            ])
            ->assertRedirect(route('pub-golf.index'));

        $this->assertDatabaseCount('pub_golf_drink_logs', 0);
    }

    public function test_players_who_called_it_cannot_log_more_drinks(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $host->id]);
        PubGolfParticipant::factory()->left()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
        ]);

        $this->actingAs($friend)
            ->from(route('pub-golf.recap.show', $crawl))
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => PubGolfCustomDrink::factory()->create(['user_id' => $friend->id])->id,
            ])
            ->assertRedirect(route('pub-golf.recap.show', $crawl))
            ->assertSessionHasErrors(['drink' => 'You already called it on this crawl.']);

        $this->assertDatabaseCount('pub_golf_drink_logs', 0);
    }

    public function test_players_can_undo_their_last_drink(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);
        $keep = PubGolfDrinkLog::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'drink_id' => PubGolfCustomDrink::factory()->create([
                'user_id' => $user->id,
                'name' => 'Castle Lite',
            ]),
        ]);
        PubGolfDrinkLog::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'drink_id' => PubGolfCustomDrink::factory()->create([
                'user_id' => $user->id,
                'name' => 'Savanna Dry',
                'category' => PubGolfDrinkCategory::Cider,
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('pub-golf.drinks.undo', $crawl))
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHas('status', 'Savanna Dry undone.');

        $this->assertDatabaseCount('pub_golf_drink_logs', 1);
        $this->assertModelExists($keep);
    }

    public function test_undoing_with_no_drinks_is_rejected(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.drinks.undo', $crawl))
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['drink' => 'You have not logged a drink to undo.']);
    }

    public function test_undo_only_removes_the_current_players_last_drink(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $host->id]);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
        ]);
        $theirs = PubGolfDrinkLog::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
            'drink_id' => PubGolfCustomDrink::factory()->create([
                'user_id' => $friend->id,
                'name' => 'Brutal Fruit',
            ]),
        ]);

        $this->actingAs($host)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.drinks.undo', $crawl))
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['drink' => 'You have not logged a drink to undo.']);

        $this->assertModelExists($theirs);
    }

    public function test_the_recap_counts_drinks_per_hour_over_the_session(): void
    {
        $this->travelTo('2026-09-08 18:00:00');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $crawl->participants()->where('user_id', $user->id)->update(['joined_at' => now()]);

        PubGolfDrinkLog::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'drink_id' => PubGolfCustomDrink::factory()->create([
                'user_id' => $user->id,
                'name' => 'Castle Lager',
            ]),
            'created_at' => now(),
        ]);

        $this->travel(2)->hours();

        $this->actingAs($user)
            ->post(route('pub-golf.leave.store', $crawl))
            ->assertRedirect(route('pub-golf.recap.show', $crawl));

        $this->actingAs($user)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertOk()
            ->assertSee('2h 0m')
            ->assertSee('Castle Lager')
            ->assertDontSee('Water')
            ->assertSee('0.5')
            ->assertSee('Sipping');
    }

    public function test_the_drink_picker_starts_empty_and_asks_for_a_photo(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('No drinks yet. Add one below.')
            ->assertSee("Don't see your drink?", false)
            ->assertSee('Take a photo or upload a JPEG, PNG, or WebP. HEIC is not allowed.')
            ->assertDontSee('Search South African drinks')
            ->assertDontSee('>Soft<', false)
            ->assertDontSee('images/pub-golf/castle_lager')
            ->assertSee('Add drink')
            ->assertSee('Log this drink?')
            ->assertSee('Remove this drink?');
    }
}
