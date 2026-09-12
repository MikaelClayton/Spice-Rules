<?php

namespace Tests\Feature;

use App\Enums\WicketFineType;
use App\Enums\WicketGroupRole;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class WicketTournamentTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_guests_cannot_update_a_group(): void
    {
        $group = WicketGroup::factory()->create();

        $this->patch(route('wickets.update', $group), [
            'is_tournament' => '1',
        ])->assertRedirect(route('login'));

        $this->assertFalse($group->fresh()->isTournament());
    }

    public function test_users_can_create_a_tournament_group(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('wickets.store'), [
                'name' => 'Club champs',
                'is_tournament' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wicket_groups', [
            'name' => 'Club champs',
            'is_tournament' => true,
            'notify_all_on_fine' => false,
        ]);
    }

    public function test_owners_can_turn_tournament_mode_on(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('wickets.update', $group), [
                'is_tournament' => '1',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHas('status', 'Group settings saved.');

        $this->assertTrue($group->fresh()->isTournament());
    }

    public function test_owners_can_turn_group_fine_notifications_on(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('wickets.update', $group), [
                'is_tournament' => '0',
                'notify_all_on_fine' => '1',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHas('status', 'Group settings saved.');

        $this->assertTrue($group->fresh()->notifiesAllOnFine());
        $this->assertFalse($group->fresh()->isTournament());
    }

    public function test_members_cannot_turn_tournament_mode_on(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($member)
            ->patch(route('wickets.update', $group), [
                'is_tournament' => '1',
            ])
            ->assertForbidden();

        $this->assertFalse($group->fresh()->isTournament());
    }

    public function test_owners_can_make_a_member_fines_master(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->patch(route('wickets.members.update', [$group, $member]), [
                'role' => WicketGroupRole::FinesMaster->value,
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHas('status', 'Sam Fine is now Fines Master.');

        $this->assertDatabaseHas('user_wicket_group', [
            'wicket_group_id' => $group->id,
            'user_id' => $member->id,
            'role' => WicketGroupRole::FinesMaster->value,
        ]);
        $this->assertTrue($group->fresh()->isFinesMaster($member));
    }

    public function test_members_cannot_make_someone_fines_master(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member, $other);

        $this->actingAs($member)
            ->patch(route('wickets.members.update', [$group, $other]), [
                'role' => WicketGroupRole::FinesMaster->value,
            ])
            ->assertForbidden();

        $this->assertFalse($group->fresh()->isFinesMaster($other));
    }

    public function test_updating_a_user_who_is_not_in_the_group_returns_404(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('wickets.members.update', [$group, $stranger]), [
                'role' => WicketGroupRole::FinesMaster->value,
            ])
            ->assertNotFound();
    }

    public function test_a_tournament_hides_a_players_own_fines(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->tournamentGroup($owner, $member);
        $fines = WicketFine::factory()->ofType(WicketFineType::DownDown)->count(5)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Secret own down down',
        ]);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('data-stat-count="?"', false)
            ->assertSeeInOrder([
                'data-stat-type="sips"',
                'data-stat-type="down_down"',
                'data-stat-type="funnel"',
                'data-stat-type="shoey"',
            ], false)
            ->assertDontSee('data-stat-count="5"', false)
            ->assertDontSee('Secret own down down')
            ->assertDontSee('data-open-fine-id="'.$fines->first()->id.'"', false)
            ->assertSee('Your fines are hidden.');
    }

    public function test_a_tournament_shows_only_fines_the_viewer_gave(): void
    {
        $owner = User::factory()->create();
        $issuer = User::factory()->create(['name' => 'Alex Tee']);
        $target = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->tournamentGroup($owner, $issuer, $target);
        WicketFine::factory()->ofType(WicketFineType::DownDown)->count(5)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $target->id,
            'reason' => 'Owner down down',
        ]);
        $funnel = WicketFine::factory()->ofType(WicketFineType::Funnel)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $issuer->id,
            'issued_to_user_id' => $target->id,
            'reason' => 'Alex funnel',
        ]);

        $this->actingAs($issuer)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('Alex funnel')
            ->assertSee('data-open-fine-id="'.$funnel->id.'"', false)
            ->assertSee('data-stat-type="funnel"', false)
            ->assertSee('data-stat-count="1"', false)
            ->assertSee('data-stat-bound="at-least"', false)
            ->assertSee('>= 1')
            ->assertDontSee('Owner down down')
            ->assertDontSee('data-stat-count="5"', false);
    }

    public function test_a_tournament_owner_cannot_see_their_own_received_fines(): void
    {
        $owner = User::factory()->create(['name' => 'Alex']);
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->tournamentGroup($owner, $member);
        $fine = WicketFine::factory()->ofType(WicketFineType::Shoey)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $member->id,
            'issued_to_user_id' => $owner->id,
            'reason' => 'Secret owner shoey',
        ]);

        $this->actingAs($owner)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertDontSee('Secret owner shoey')
            ->assertDontSee('data-open-fine-id="'.$fine->id.'"', false)
            ->assertDontSee('data-stat-count="1"', false)
            ->assertSee('Your fines are hidden.');
    }

    public function test_a_tournament_shows_a_lower_bound_for_fines_the_viewer_gave(): void
    {
        $owner = User::factory()->create();
        $issuer = User::factory()->create(['name' => 'Alex Tee']);
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->tournamentGroup($owner, $issuer, $member);
        WicketFine::factory()->ofType(WicketFineType::DownDown)->count(5)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $issuer->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Alex down down',
        ]);
        WicketFine::factory()->ofType(WicketFineType::Sips, 2)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $issuer->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Alex sips',
        ]);

        $this->actingAs($issuer)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('Alex down down')
            ->assertSee('data-stat-type="down_down"', false)
            ->assertSee('data-stat-count="5"', false)
            ->assertSee('data-stat-bound="at-least"', false)
            ->assertSee('>= 5')
            ->assertSee('data-stat-type="sips"', false)
            ->assertSee('data-stat-count="2"', false)
            ->assertSee('>= 2')
            ->assertSee('data-stat-bound="hidden"', false);
    }

    public function test_a_tournament_shows_question_marks_when_the_viewer_gave_no_fines(): void
    {
        $owner = User::factory()->create();
        $issuer = User::factory()->create();
        $viewer = User::factory()->create();
        $untouched = User::factory()->create(['name' => 'Melissa Kapp']);
        $group = $this->tournamentGroup($owner, $issuer, $viewer, $untouched);
        WicketFine::factory()->ofType(WicketFineType::DownDown)->count(3)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $issuer->id,
            'issued_to_user_id' => $untouched->id,
            'reason' => 'Hidden Melissa down down',
        ]);

        $this->actingAs($viewer)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('Melissa Kapp')
            ->assertSee('data-stat-type="down_down"', false)
            ->assertSee('data-stat-type="funnel"', false)
            ->assertSee('data-stat-type="shoey"', false)
            ->assertSee('data-stat-type="sips"', false)
            ->assertSee('data-stat-bound="hidden"', false)
            ->assertDontSee('Hidden Melissa down down')
            ->assertDontSee('data-stat-count="3"', false)
            ->assertDontSee('data-stat-bound="at-least"', false);
    }

    public function test_a_tournament_owner_sees_other_players_exact_fines(): void
    {
        $owner = User::factory()->create();
        $issuer = User::factory()->create();
        $member = User::factory()->create(['name' => 'Melissa Kapp']);
        $group = $this->tournamentGroup($owner, $issuer, $member);
        WicketFine::factory()->ofType(WicketFineType::DownDown)->count(3)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $issuer->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Melissa down down',
        ]);

        $this->actingAs($owner)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('Melissa down down')
            ->assertSee('data-stat-type="down_down"', false)
            ->assertSee('data-stat-count="3"', false)
            ->assertSee('data-stat-bound="exact"', false)
            ->assertDontSee('data-stat-bound="at-least"', false)
            ->assertSee('Your fines are hidden.');
    }

    public function test_a_tournament_fines_master_sees_other_players_exact_fines(): void
    {
        $owner = User::factory()->create();
        $master = User::factory()->create(['name' => 'Alex Tee']);
        $target = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->tournamentGroup($owner, $master, $target);
        $group->users()->updateExistingPivot($master->id, [
            'role' => WicketGroupRole::FinesMaster->value,
        ]);
        WicketFine::factory()->ofType(WicketFineType::DownDown)->count(5)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $target->id,
            'reason' => 'Owner down down',
        ]);
        WicketFine::factory()->ofType(WicketFineType::Shoey)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $master->id,
            'reason' => 'Secret master shoey',
        ]);

        $this->actingAs($master)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('Owner down down')
            ->assertSee('data-stat-count="5"', false)
            ->assertSee('data-stat-bound="exact"', false)
            ->assertDontSee('data-stat-bound="at-least"', false)
            ->assertDontSee('Secret master shoey')
            ->assertSee('Your fines are hidden.')
            ->assertSee('fines master');
    }

    public function test_a_tournament_fine_sends_a_hidden_push_notification(): void
    {
        $this->enableFirebase();
        $this->fakeFcm();

        $owner = User::factory()->create(['name' => 'Alex']);
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->tournamentGroup($owner, $member);
        $targetToken = DeviceToken::factory()->create([
            'user_id' => $member->id,
            'token' => str_repeat('t', 40),
        ]);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'type' => WicketFineType::Sips->value,
                'sips' => 2,
                'reason' => 'Late to the first tee',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
            && $request['message']['token'] === $targetToken->token
            && $request['message']['notification']['title'] === 'Club day'
            && $request['message']['notification']['body'] === "You've been fined 👀");
    }

    public function test_a_tournament_group_can_still_notify_everyone_else(): void
    {
        $this->enableFirebase();
        $this->fakeFcm();

        $owner = User::factory()->create(['name' => 'Alex']);
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $bystander = User::factory()->create(['name' => 'Pat']);
        $group = $this->tournamentGroup($owner, $member, $bystander);
        $group->update(['notify_all_on_fine' => true]);
        $targetToken = DeviceToken::factory()->create([
            'user_id' => $member->id,
            'token' => str_repeat('t', 40),
        ]);
        $bystanderToken = DeviceToken::factory()->create([
            'user_id' => $bystander->id,
            'token' => str_repeat('b', 40),
        ]);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'type' => WicketFineType::Sips->value,
                'sips' => 2,
                'reason' => 'Late to the first tee',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
            && $request['message']['token'] === $targetToken->token
            && $request['message']['notification']['body'] === "You've been fined 👀");
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
            && $request['message']['token'] === $bystanderToken->token
            && $request['message']['notification']['body'] === 'Alex fined Sam Fine 2 sips for Late to the first tee');
    }

    private function fakeFcm(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send' => Http::response([
                'name' => 'projects/spice-rules-test/messages/1',
            ]),
        ]);
    }

    private function groupWithMembers(User $owner, User ...$members): WicketGroup
    {
        return $this->makeGroup($owner, $members, false);
    }

    private function tournamentGroup(User $owner, User ...$members): WicketGroup
    {
        return $this->makeGroup($owner, $members, true);
    }

    /**
     * @param  list<User>  $members
     */
    private function makeGroup(User $owner, array $members, bool $tournament): WicketGroup
    {
        $group = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Club day',
            'is_tournament' => $tournament,
        ]);

        $group->users()->syncWithoutDetaching(collect($members)->pluck('id')->all());

        return $group;
    }
}
