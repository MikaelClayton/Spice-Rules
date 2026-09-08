<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WicketGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WicketGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_wickets(): void
    {
        $this->get(route('wickets.index'))->assertRedirect(route('login'));
        $this->get(route('wickets.create'))->assertRedirect(route('login'));
        $this->post(route('wickets.store'), ['name' => 'Club day'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('wicket_groups', 0);
    }

    public function test_authenticated_users_see_an_empty_wickets_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('wickets.index'))
            ->assertOk()
            ->assertSee('Wickets')
            ->assertSee('No groups yet')
            ->assertSee('Create a group');
    }

    public function test_users_can_create_a_group_and_become_a_member(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('wickets.store'), [
                'name' => 'Club day',
            ]);

        $group = WicketGroup::query()->first();

        $this->assertNotNull($group);
        $response->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']));
        $this->assertDatabaseHas('wicket_groups', [
            'id' => $group->id,
            'name' => 'Club day',
            'user_id' => $user->id,
            'is_tournament' => false,
        ]);
        $this->assertDatabaseHas('user_wicket_group', [
            'wicket_group_id' => $group->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame(1, $group->users()->count());
    }

    public function test_a_blank_group_name_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('wickets.create'))
            ->post(route('wickets.store'), [
                'name' => '   ',
            ])
            ->assertRedirect(route('wickets.create'))
            ->assertSessionHasErrors(['name' => 'Give the group a name.']);

        $this->assertDatabaseCount('wicket_groups', 0);
    }

    public function test_users_see_only_groups_they_belong_to(): void
    {
        $user = User::factory()->create();
        WicketGroup::factory()->create([
            'user_id' => $user->id,
            'name' => 'Our lot',
        ]);
        WicketGroup::factory()->create([
            'name' => 'Secret lot',
        ]);

        $this->actingAs($user)
            ->get(route('wickets.index'))
            ->assertOk()
            ->assertSee('Our lot')
            ->assertDontSee('Secret lot');
    }

    public function test_members_can_view_a_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($member)
            ->get(route('wickets.show', $group))
            ->assertOk()
            ->assertSee($group->name)
            ->assertSee('Sam Fine')
            ->assertSee('Give a fine')
            ->assertSee('Activity')
            ->assertDontSee('Recent fines')
            ->assertDontSee('(you)')
            ->assertSee('data-sip-stepper', false)
            ->assertSee('data-people-picker', false)
            ->assertSee('Search players')
            ->assertSee('Drink sips')
            ->assertSee('Add players')
            ->assertDontSee('Remove');
    }

    public function test_non_members_cannot_view_a_group(): void
    {
        $group = WicketGroup::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('wickets.show', $group))
            ->assertForbidden();
    }

    public function test_owners_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $player = User::factory()->create(['name' => 'Alex']);
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('wickets.members.store', $group), [
                'user_ids' => [$player->id],
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']));

        $this->assertTrue($group->hasMember($player));
        $this->assertDatabaseHas('user_wicket_group', [
            'wicket_group_id' => $group->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_members_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $player = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($member)
            ->post(route('wickets.members.store', $group), [
                'user_ids' => [$player->id],
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']));

        $this->assertTrue($group->hasMember($player));
        $this->assertDatabaseHas('user_wicket_group', [
            'wicket_group_id' => $group->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_non_members_cannot_add_a_member(): void
    {
        $group = WicketGroup::factory()->create();
        $stranger = User::factory()->create();
        $player = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('wickets.members.store', $group), [
                'user_ids' => [$player->id],
            ])
            ->assertForbidden();

        $this->assertFalse($group->hasMember($player));
    }

    public function test_adding_an_existing_member_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->post(route('wickets.members.store', $group), [
                'user_ids' => [$member->id],
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHasErrors(['user_ids' => 'That person is already in this group.']);
    }

    public function test_adding_nobody_is_rejected(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->post(route('wickets.members.store', $group), [])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHasErrors(['user_ids' => 'Pick at least one person to add.']);
    }

    public function test_owners_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Sam Fine']);
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->delete(route('wickets.members.destroy', [$group, $member]))
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']));

        $this->assertFalse($group->fresh()->hasMember($member));
    }

    public function test_owners_cannot_remove_themselves(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->delete(route('wickets.members.destroy', [$group, $owner]))
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHasErrors(['user' => 'The group owner cannot be removed.']);

        $this->assertTrue($group->fresh()->hasMember($owner));
    }

    public function test_non_owners_cannot_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member, $other);

        $this->actingAs($member)
            ->delete(route('wickets.members.destroy', [$group, $other]))
            ->assertForbidden();

        $this->assertTrue($group->fresh()->hasMember($other));
    }

    public function test_removing_a_user_who_is_not_in_the_group_returns_404(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $group = WicketGroup::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->delete(route('wickets.members.destroy', [$group, $stranger]))
            ->assertNotFound();
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
