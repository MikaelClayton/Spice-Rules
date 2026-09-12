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
        $this->delete('/wickets/1')->assertRedirect(route('login'));
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
            'is_active' => true,
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
            ->assertDontSee('Remove')
            ->assertDontSee('Delete group')
            ->assertDontSee('Notify the group');
    }

    public function test_the_people_tab_uses_the_player_search_to_add_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        User::factory()->create(['name' => 'Alex Tee']);
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->get(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSee('Add players')
            ->assertSeeInOrder(['Players', 'Add players', 'Group settings', 'Delete group'])
            ->assertSee('Search and pick one or more players.')
            ->assertSee('data-people-search', false)
            ->assertSee('name="user_ids[]"', false)
            ->assertSee('Alex Tee');
    }

    public function test_owners_see_the_delete_group_warning_on_the_people_tab(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Club day',
        ]);

        $this->actingAs($owner)
            ->get(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertOk()
            ->assertSee('Delete group')
            ->assertSee('nobody will see this group')
            ->assertSee('Type')
            ->assertSee('Notify the group');
    }

    public function test_non_members_are_redirected_to_the_groups_page(): void
    {
        $group = WicketGroup::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('wickets.show', $group))
            ->assertRedirect(route('wickets.index'));
    }

    public function test_an_unknown_group_id_redirects_to_the_groups_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/wickets/99999')
            ->assertRedirect(route('wickets.index'));
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
            ->assertRedirect(route('wickets.index'));

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

    public function test_owners_can_delete_a_group_by_confirming_its_name(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($owner)
            ->delete(route('wickets.destroy', $group), [
                'name' => 'Club day',
            ])
            ->assertRedirect(route('wickets.index'))
            ->assertSessionHas('status', 'Group deleted.');

        $this->assertDatabaseHas('wicket_groups', [
            'id' => $group->id,
            'name' => 'Club day',
            'is_active' => false,
        ]);
        $this->assertTrue($group->fresh()->hasMember($member));

        $this->actingAs($owner)
            ->get(route('wickets.index'))
            ->assertOk()
            ->assertSee('No groups yet')
            ->assertDontSee('Club day');
    }

    public function test_a_group_is_not_deleted_when_the_name_does_not_match(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Club day',
        ]);

        $this->actingAs($owner)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->delete(route('wickets.destroy', $group), [
                'name' => 'Wrong name',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHasErrors(['name' => 'Type the group name exactly to confirm.']);

        $this->assertDatabaseHas('wicket_groups', [
            'id' => $group->id,
            'is_active' => true,
        ]);
    }

    public function test_a_blank_confirmation_name_is_rejected(): void
    {
        $owner = User::factory()->create();
        $group = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Club day',
        ]);

        $this->actingAs($owner)
            ->from(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->delete(route('wickets.destroy', $group), [
                'name' => '   ',
            ])
            ->assertRedirect(route('wickets.show', ['wicketGroup' => $group, 'tab' => 'people']))
            ->assertSessionHasErrors(['name' => 'Type the group name to confirm.']);

        $this->assertDatabaseHas('wicket_groups', [
            'id' => $group->id,
            'is_active' => true,
        ]);
    }

    public function test_members_cannot_delete_a_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->groupWithMembers($owner, $member);

        $this->actingAs($member)
            ->delete(route('wickets.destroy', $group), [
                'name' => 'Club day',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('wicket_groups', [
            'id' => $group->id,
            'is_active' => true,
        ]);
    }

    public function test_inactive_groups_are_hidden_from_members_and_the_list(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $active = WicketGroup::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Live lot',
        ]);
        $inactive = WicketGroup::factory()->inactive()->create([
            'user_id' => $owner->id,
            'name' => 'Old lot',
        ]);
        $active->users()->syncWithoutDetaching([$member->id]);
        $inactive->users()->syncWithoutDetaching([$member->id]);

        $this->actingAs($member)
            ->get(route('wickets.index'))
            ->assertOk()
            ->assertSee('Live lot')
            ->assertDontSee('Old lot');

        $this->actingAs($member)
            ->get(route('wickets.show', $inactive))
            ->assertRedirect(route('wickets.index'));

        $this->actingAs($owner)
            ->get(route('wickets.show', $inactive))
            ->assertRedirect(route('wickets.index'));
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
