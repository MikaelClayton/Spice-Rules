<?php

namespace Tests\Feature;

use App\Models\FitIshSession;
use App\Models\FitIshStudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitIshStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_a_studio(): void
    {
        $this->post(route('profile.fit-ish.studios.store'), [
            'studio_id' => 5061,
            'name' => 'F45 Faerie Glen',
            'code' => 'ojb7',
            'timezone' => 'Africa/Johannesburg',
        ])->assertRedirect(route('login'));
    }

    public function test_users_can_create_a_studio_and_it_is_linked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.fit-ish.studios.store'), [
                'studio_id' => 5061,
                'name' => 'F45 Faerie Glen',
                'code' => 'OJB7',
                'timezone' => 'Africa/Johannesburg',
                'is_loaner' => '0',
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']))
            ->assertSessionHas('status');

        $studio = FitIshStudio::query()->first();

        $this->assertNotNull($studio);
        $this->assertSame(5061, $studio->external_id);
        $this->assertSame('ojb7', $studio->code);
        $this->assertTrue($user->fresh()->fitIshStudios->contains($studio));
    }

    public function test_users_can_update_a_studio(): void
    {
        $user = User::factory()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();

        $this->actingAs($user)
            ->patch(route('profile.fit-ish.studios.update', $studio), [
                'studio_id' => 5061,
                'name' => 'F45 Faerie Glen East',
                'code' => 'ojb7',
                'timezone' => 'Africa/Johannesburg',
                'is_loaner' => '1',
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']));

        $this->assertSame('F45 Faerie Glen East', $studio->fresh()->name);
        $this->assertTrue($studio->fresh()->is_loaner);
    }

    public function test_users_can_delete_a_studio_without_sessions(): void
    {
        $user = User::factory()->create();
        $studio = FitIshStudio::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.fit-ish.studios.destroy', $studio))
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']));

        $this->assertDatabaseMissing('fit_ish_studios', ['id' => $studio->id]);
    }

    public function test_studios_with_sessions_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $studio = FitIshStudio::factory()->create();
        FitIshSession::factory()->create([
            'fit_ish_studio_id' => $studio->id,
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit', ['tab' => 'fit-ish']))
            ->delete(route('profile.fit-ish.studios.destroy', $studio))
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']))
            ->assertSessionHasErrors('studio');

        $this->assertDatabaseHas('fit_ish_studios', ['id' => $studio->id]);
    }

    public function test_users_can_save_fit_ish_settings(): void
    {
        $user = User::factory()->create();
        $studio = FitIshStudio::factory()->faerieGlen()->create();

        $this->actingAs($user)
            ->patch(route('profile.fit-ish.update'), [
                'fit_ish_user_id' => '13138221',
                'fit_ish_serial' => '1352',
                'studio_ids' => [$studio->id],
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']));

        $user->refresh();

        $this->assertSame('13138221', $user->fit_ish_user_id);
        $this->assertSame('1352', $user->fit_ish_serial);
        $this->assertTrue($user->fitIshStudios->contains($studio));
    }

    public function test_duplicate_lionheart_user_ids_are_rejected(): void
    {
        User::factory()->withFitIsh('13138221')->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit', ['tab' => 'fit-ish']))
            ->patch(route('profile.fit-ish.update'), [
                'fit_ish_user_id' => '13138221',
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'fit-ish']))
            ->assertSessionHasErrors('fit_ish_user_id');
    }
}
