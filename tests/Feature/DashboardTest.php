<?php

namespace Tests\Feature;

use App\Models\FitIshSession;
use App\Models\FitIshStudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_see_the_item_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Clubhouse')
            ->assertSee('Pick a board and see how the club is doing.')
            ->assertSee('Wickets')
            ->assertSee('Pub Golf')
            ->assertDontSee('GeoGuessr')
            ->assertDontSee('Fit-Ish')
            ->assertDontSee('Trivia')
            ->assertDontSee('Word Rush')
            ->assertDontSee('Admin');
    }

    public function test_geoguessr_tile_shows_when_the_profile_is_active(): void
    {
        $user = User::factory()->withActiveGeoguessr()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('GeoGuessr')
            ->assertSee("See how everyone did on today's round.");
    }

    public function test_fit_ish_tile_shows_when_a_user_id_is_saved(): void
    {
        $user = User::factory()->withFitIsh()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fit-Ish')
            ->assertSee('Lionheart points');
    }

    public function test_authenticated_users_can_open_todays_geoguessr_results(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('geoguessr.index'))
            ->assertOk()
            ->assertSee('GeoGuessr')
            ->assertSee('Nobody has logged a score yet today.');
    }

    public function test_fit_ish_board_redirects_until_a_user_id_is_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('fit-ish.index'))
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']));
    }

    public function test_fit_ish_board_opens_when_a_user_id_is_saved(): void
    {
        $user = User::factory()->withFitIsh()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();
        $user->fitIshStudios()->attach($studio);
        FitIshSession::factory()->create([
            'user_id' => $user->id,
            'fit_ish_studio_id' => $studio->id,
            'class_date' => today()->toDateString(),
            'session_id' => today()->toDateString().'_0600:studio:ojb7:serial:1352',
        ]);

        $this->actingAs($user)
            ->get(route('fit-ish.index'))
            ->assertOk()
            ->assertSee('Fit-Ish')
            ->assertSee('Today')
            ->assertSee('Weekly')
            ->assertSee($user->name)
            ->assertSee('48.3');
    }
}
