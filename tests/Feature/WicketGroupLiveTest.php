<?php

namespace Tests\Feature;

use App\Enums\WicketFineType;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WicketGroupLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_poll_a_group_board(): void
    {
        $group = WicketGroup::factory()->create();

        $this->getJson(route('wickets.live', $group))
            ->assertUnauthorized();
    }

    public function test_live_board_includes_a_new_fine(): void
    {
        $owner = User::factory()->create(['name' => 'Alex']);
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);
        WicketFine::factory()->ofType(WicketFineType::Sips, 3)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Forgot their whites',
        ]);

        $response = $this->actingAs($member)
            ->getJson(route('wickets.live', $group));

        $response->assertOk();
        $this->assertNotSame('', $response->json('revision'));
        $this->assertStringContainsString('Forgot their whites', (string) $response->json('regions.board'));
        $this->assertStringContainsString('Sam Fine', (string) $response->json('regions.board'));
        $this->assertStringContainsString('Forgot their whites', (string) $response->json('regions.drink'));
        $this->assertStringContainsString('>3</dd>', (string) $response->json('regions.owe'));
    }

    public function test_matching_revision_omits_board_html(): void
    {
        $owner = User::factory()->create();
        $group = $this->groupWithMembers($owner);
        $revision = $this->actingAs($owner)
            ->getJson(route('wickets.live', $group))
            ->assertOk()
            ->json('revision');

        $this->actingAs($owner)
            ->getJson(route('wickets.live', ['wicketGroup' => $group, 'revision' => $revision]))
            ->assertOk()
            ->assertExactJson(['revision' => $revision]);
    }

    public function test_outsiders_are_redirected_from_live_board(): void
    {
        $group = WicketGroup::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson(route('wickets.live', $group))
            ->assertRedirect(route('wickets.index'));
    }

    public function test_a_tournament_live_board_hides_the_viewers_own_fines(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Club day',
            'is_tournament' => true,
        ]);
        $group->users()->syncWithoutDetaching([$member->id]);
        WicketFine::factory()->ofType(WicketFineType::DownDown)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Secret own down down',
        ]);

        $response = $this->actingAs($member)
            ->getJson(route('wickets.live', $group));

        $response->assertOk();
        $this->assertStringContainsString('Your fines are hidden.', (string) $response->json('regions.board'));
        $this->assertStringNotContainsString('Secret own down down', (string) $response->json('regions.board'));
        $this->assertStringNotContainsString('Secret own down down', (string) $response->json('regions.drink'));
    }

    public function test_the_group_page_is_wired_to_poll_live_board(): void
    {
        $owner = User::factory()->create();
        $group = $this->groupWithMembers($owner);

        $this->actingAs($owner)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('data-poll-url="'.e(route('wickets.live', $group)).'"', false)
            ->assertSee('data-live-region="owe"', false)
            ->assertSee('data-live-region="board"', false)
            ->assertSee('data-live-region="drink"', false);
    }

    private function groupWithMembers(User $owner, User ...$members): WicketGroup
    {
        $group = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Club day',
        ]);

        $group->users()->syncWithoutDetaching(collect($members)->pluck('id')->all());

        return $group;
    }
}
