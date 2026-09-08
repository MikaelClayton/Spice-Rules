<?php

namespace Tests\Feature;

use App\Enums\WicketFineType;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\WicketFine;
use App\Models\WicketGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class WicketFineTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_guests_cannot_issue_a_fine(): void
    {
        $group = WicketGroup::factory()->create();

        $this->post(route('wickets.fines.store', $group), [
            'issued_to_user_id' => $group->user_id,
            'type' => WicketFineType::Sips->value,
            'sips' => 1,
            'reason' => 'Nope',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('wicket_fines', 0);
    }

    public function test_members_can_issue_a_sip_fine(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'type' => WicketFineType::Sips->value,
                'sips' => 2,
                'reason' => 'Late to the first tee',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        $this->assertDatabaseHas('wicket_fines', [
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'type' => WicketFineType::Sips->value,
            'reason' => 'Late to the first tee',
            'sips_owed' => 2,
            'sips_completed' => 0,
            'completed_at' => null,
        ]);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('Late to the first tee')
            ->assertSee('2 sips');
    }

    public function test_members_can_issue_a_fine_to_more_than_one_person(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create(['name' => 'Sam Fine']);
        $second = User::factory()->create(['name' => 'Alex Tee']);
        $group = $this->groupWithMembers($owner, $first, $second);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_ids' => [$first->id, $second->id],
                'type' => WicketFineType::DownDown->value,
                'reason' => 'Lost the honours',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']))
            ->assertSessionHas('status', 'Fine given to 2 players.');

        $this->assertDatabaseCount('wicket_fines', 2);
        $this->assertDatabaseHas('wicket_fines', [
            'issued_to_user_id' => $first->id,
            'type' => WicketFineType::DownDown->value,
            'reason' => 'Lost the honours',
        ]);
        $this->assertDatabaseHas('wicket_fines', [
            'issued_to_user_id' => $second->id,
            'type' => WicketFineType::DownDown->value,
            'reason' => 'Lost the honours',
        ]);
    }

    public function test_members_can_issue_sips_from_the_stepper(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'sips' => 3,
                'reason' => 'Talking in the backswing',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        $this->assertDatabaseHas('wicket_fines', [
            'wicket_group_id' => $group->id,
            'issued_to_user_id' => $member->id,
            'type' => WicketFineType::Sips->value,
            'sips_owed' => 3,
            'reason' => 'Talking in the backswing',
        ]);
    }

    public function test_eight_outstanding_sips_become_a_down_down(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);
        WicketFine::factory()->ofType(WicketFineType::Sips, 7)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Slow play',
        ]);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'type' => WicketFineType::Sips->value,
                'sips' => 1,
                'reason' => 'One more',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        $this->assertSame(0, WicketFine::query()->where('type', WicketFineType::Sips)->whereNull('completed_at')->count());
        $this->assertDatabaseHas('wicket_fines', [
            'wicket_group_id' => $group->id,
            'issued_to_user_id' => $member->id,
            'type' => WicketFineType::DownDown->value,
            'reason' => '8 sips',
            'completed_at' => null,
        ]);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('8 sips became a down down.')
            ->assertSee('Down down');
    }

    public function test_sips_past_eight_leave_the_remainder(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        WicketFine::factory()->ofType(WicketFineType::Sips, 5)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'type' => WicketFineType::Sips->value,
                'sips' => 4,
                'reason' => 'Pushing it',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        $this->assertSame(1, WicketFine::query()->where('type', WicketFineType::DownDown)->whereNull('completed_at')->count());
        $remainingSipFine = WicketFine::query()
            ->where('type', WicketFineType::Sips)
            ->whereNull('completed_at')
            ->first();

        $this->assertNotNull($remainingSipFine);
        $this->assertSame(1, $remainingSipFine->remainingSips());
    }

    public function test_members_can_issue_a_shoey(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'type' => WicketFineType::Shoey->value,
                'reason' => 'Walking on the green',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        $this->assertDatabaseHas('wicket_fines', [
            'wicket_group_id' => $group->id,
            'issued_to_user_id' => $member->id,
            'type' => WicketFineType::Shoey->value,
            'reason' => 'Walking on the green',
            'sips_owed' => 0,
            'completed_at' => null,
        ]);
    }

    public function test_a_special_fine_is_used_when_sips_are_also_posted(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'punishment' => WicketFineType::Funnel->value,
                'sips' => 4,
                'reason' => 'Three putt',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        $this->assertDatabaseHas('wicket_fines', [
            'wicket_group_id' => $group->id,
            'issued_to_user_id' => $member->id,
            'type' => WicketFineType::Funnel->value,
            'sips_owed' => 0,
            'reason' => 'Three putt',
        ]);
    }

    public function test_an_empty_fine_payload_is_rejected(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'fine']))
            ->post(route('wickets.fines.store', $group), [])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'fine']))
            ->assertSessionHasErrors([
                'issued_to_user_ids' => 'Pick someone to fine.',
                'type' => 'Pick a fine.',
                'reason' => 'Give a reason for the fine.',
            ]);

        $this->assertDatabaseCount('wicket_fines', 0);
    }

    public function test_fining_someone_outside_the_group_is_rejected(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'fine']))
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $stranger->id,
                'type' => WicketFineType::Sips->value,
                'sips' => 1,
                'reason' => 'Not even here',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'fine']))
            ->assertSessionHasErrors(['issued_to_user_ids.0' => 'That person is not in this group.']);

        $this->assertDatabaseCount('wicket_fines', 0);
    }

    public function test_non_members_cannot_issue_a_fine(): void
    {
        $group = WicketGroup::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $group->user_id,
                'type' => WicketFineType::Sips->value,
                'sips' => 1,
                'reason' => 'Nope',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('wicket_fines', 0);
    }

    public function test_members_can_drink_sips_to_reduce_outstanding_fines(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        $fine = WicketFine::factory()->ofType(WicketFineType::Sips, 4)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Slow play',
        ]);

        $this->actingAs($member)
            ->post(route('wickets.sips.store', $group), [
                'sips' => 3,
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'drink']));

        $fine->refresh();
        $this->assertSame(3, $fine->sips_completed);
        $this->assertSame(1, $fine->remainingSips());
        $this->assertNull($fine->completed_at);
        $this->assertDatabaseHas('wicket_sip_logs', [
            'wicket_group_id' => $group->id,
            'user_id' => $member->id,
            'sips' => 3,
        ]);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('Activity')
            ->assertSeeInOrder(['Drank 3 sips', 'Slow play']);
    }

    public function test_the_board_lists_a_players_open_fines_with_reasons(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);
        $openFine = WicketFine::factory()->ofType(WicketFineType::Sips, 2)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Still owing this one',
        ]);
        $doneFine = WicketFine::factory()->ofType(WicketFineType::Sips, 1)->completed()->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Already drunk this one',
        ]);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('data-player-fines="'.$member->id.'"', false)
            ->assertSee('data-open-fine-id="'.$openFine->id.'"', false)
            ->assertSee('Still owing this one')
            ->assertSee('data-stat-type="sips"', false)
            ->assertSee('data-stat-count="2"', false)
            ->assertSee('data-stat-bound="exact"', false)
            ->assertDontSee('data-stat-bound="at-least"', false)
            ->assertDontSee('Your fines are hidden.')
            ->assertDontSee('data-open-fine-id="'.$doneFine->id.'"', false);
    }

    public function test_repeated_specials_show_a_count_on_the_board(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);
        WicketFine::factory()->ofType(WicketFineType::DownDown)->count(5)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
        ]);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('data-stat-type="down_down"', false)
            ->assertSee('data-stat-count="5"', false)
            ->assertSee('data-stat-bound="exact"', false)
            ->assertDontSee('data-stat-bound="at-least"', false)
            ->assertDontSee('Your fines are hidden.')
            ->assertSee('Down down');
    }

    public function test_sips_are_applied_to_the_oldest_fine_first(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        $older = WicketFine::factory()->ofType(WicketFineType::Sips, 2)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'First offence',
        ]);
        $newer = WicketFine::factory()->ofType(WicketFineType::Sips, 3)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Second offence',
        ]);

        $this->actingAs($member)
            ->post(route('wickets.sips.store', $group), [
                'sips' => 3,
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'drink']));

        $older->refresh();
        $newer->refresh();

        $this->assertSame(2, $older->sips_completed);
        $this->assertNotNull($older->completed_at);
        $this->assertSame(1, $newer->sips_completed);
        $this->assertSame(2, $newer->remainingSips());
        $this->assertNull($newer->completed_at);
    }

    public function test_drinking_more_sips_than_outstanding_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        WicketFine::factory()->ofType(WicketFineType::Sips, 2)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
        ]);

        $this->actingAs($member)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'drink']))
            ->post(route('wickets.sips.store', $group), [
                'sips' => 3,
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'drink']))
            ->assertSessionHasErrors(['sips' => 'You only have 2 sips left.']);

        $this->assertDatabaseCount('wicket_sip_logs', 0);
        $this->assertSame(0, WicketFine::query()->first()->sips_completed);
    }

    public function test_drinking_is_rejected_when_there_are_no_sip_fines(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        WicketFine::factory()->ofType(WicketFineType::Shoey)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Walking on the green',
        ]);

        $this->actingAs($member)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'drink']))
            ->post(route('wickets.sips.store', $group), [
                'sips' => 1,
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'drink']))
            ->assertSessionHasErrors(['sips' => 'You have no sip fines left to drink.']);

        $this->assertDatabaseCount('wicket_sip_logs', 0);
    }

    public function test_members_can_complete_their_own_special_fine(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        $fine = WicketFine::factory()->ofType(WicketFineType::Shoey)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => 'Walking on the green',
        ]);

        $this->actingAs($member)
            ->post(route('wickets.fines.completions.store', [$group, $fine]))
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'drink']));

        $this->assertNotNull($fine->fresh()->completed_at);
    }

    public function test_members_cannot_complete_someone_elses_special_fine(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        $fine = WicketFine::factory()->ofType(WicketFineType::Funnel)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $member->id,
            'issued_to_user_id' => $owner->id,
            'reason' => 'Birthday',
        ]);

        $this->actingAs($member)
            ->post(route('wickets.fines.completions.store', [$group, $fine]))
            ->assertForbidden();

        $this->assertNull($fine->fresh()->completed_at);
    }

    public function test_sip_fines_cannot_be_completed_without_drinking(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        $fine = WicketFine::factory()->ofType(WicketFineType::Sips, 3)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
        ]);

        $this->actingAs($member)
            ->post(route('wickets.fines.completions.store', [$group, $fine]))
            ->assertForbidden();

        $this->assertNull($fine->fresh()->completed_at);
    }

    public function test_a_completed_special_cannot_be_completed_again(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        $fine = WicketFine::factory()->ofType(WicketFineType::DownDown)->completed()->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
        ]);

        $this->actingAs($member)
            ->post(route('wickets.fines.completions.store', [$group, $fine]))
            ->assertForbidden();
    }

    public function test_a_fine_from_another_group_returns_404(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        $otherGroup = $this->groupWithMembers($owner, $member);
        $fine = WicketFine::factory()->ofType(WicketFineType::Shoey)->create([
            'wicket_group_id' => $otherGroup->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
        ]);

        $this->actingAs($member)
            ->post(route('wickets.fines.completions.store', [$group, $fine]))
            ->assertNotFound();

        $this->assertNull($fine->fresh()->completed_at);
    }

    public function test_non_members_cannot_record_sips(): void
    {
        $group = WicketGroup::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('wickets.sips.store', $group), [
                'sips' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('wicket_sip_logs', 0);
    }

    public function test_fine_reasons_are_escaped_on_the_board(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        WicketFine::factory()->ofType(WicketFineType::Sips, 1)->create([
            'wicket_group_id' => $group->id,
            'issued_by_user_id' => $owner->id,
            'issued_to_user_id' => $member->id,
            'reason' => '<script>alert("xss")</script>',
        ]);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee('<script>alert("xss")</script>')
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_the_person_fined_gets_a_push_notification(): void
    {
        $this->enableFirebase();
        $this->fakeFcm();

        $owner = User::factory()->create(['name' => 'Alex']);
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);
        $targetToken = DeviceToken::factory()->create([
            'user_id' => $member->id,
            'token' => str_repeat('t', 40),
        ]);
        $issuerToken = DeviceToken::factory()->create([
            'user_id' => $owner->id,
            'token' => str_repeat('i', 40),
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
            && $request['message']['notification']['body'] === 'Alex fined you 2 sips. Late to the first tee');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $issuerToken->token);
    }

    public function test_a_self_fine_does_not_send_a_push_notification(): void
    {
        $this->enableFirebase();
        Http::preventStrayRequests();

        $owner = User::factory()->create(['name' => 'Alex']);
        $group = $this->groupWithMembers($owner);
        DeviceToken::factory()->create([
            'user_id' => $owner->id,
            'token' => str_repeat('t', 40),
        ]);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $owner->id,
                'type' => WicketFineType::Shoey->value,
                'reason' => 'Forgot the tees',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));

        $this->assertDatabaseHas('wicket_fines', [
            'issued_to_user_id' => $owner->id,
            'issued_by_user_id' => $owner->id,
            'type' => WicketFineType::Shoey->value,
        ]);
    }

    public function test_a_fine_does_not_send_push_when_firebase_is_not_configured(): void
    {
        Http::preventStrayRequests();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);
        DeviceToken::factory()->create(['user_id' => $member->id]);

        $this->actingAs($owner)
            ->post(route('wickets.fines.store', $group), [
                'issued_to_user_id' => $member->id,
                'type' => WicketFineType::Sips->value,
                'sips' => 1,
                'reason' => 'Late',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'board']));
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
        $group = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Club day',
        ]);

        $group->users()->syncWithoutDetaching(collect($members)->pluck('id')->all());

        return $group;
    }
}
