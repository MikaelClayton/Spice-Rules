<?php

namespace Tests\Feature;

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
            ->assertSee('Today')
            ->assertSee('GeoGuessr')
            ->assertSee('Trivia')
            ->assertSee('Word Rush');
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
}
