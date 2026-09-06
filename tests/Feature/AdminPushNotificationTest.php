<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class AdminPushNotificationTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_guests_are_redirected_from_admin(): void
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_open_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Admin');

        $this->actingAs($user)
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    public function test_the_admin_can_open_the_push_notifications_tab(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin');

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Push notifications')
            ->assertSee('Send test ping')
            ->assertSee('Overview');
    }

    public function test_regular_users_cannot_send_a_test_ping(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.push.send'), [
                'audience' => 'me',
                'title' => 'Spice Rules',
                'body' => 'This is a test ping.',
            ])
            ->assertForbidden();
    }

    public function test_the_admin_can_send_a_test_ping_to_their_own_devices(): void
    {
        $this->enableFirebase();
        Cache::flush();

        $admin = $this->admin();
        $other = User::factory()->create();
        $mine = DeviceToken::factory()->create([
            'user_id' => $admin->id,
            'token' => str_repeat('a', 40),
        ]);
        $theirs = DeviceToken::factory()->create([
            'user_id' => $other->id,
            'token' => str_repeat('b', 40),
        ]);

        $this->fakeFcm();

        $this->actingAs($admin)
            ->post(route('admin.push.send'), [
                'audience' => 'me',
                'title' => 'Spice Rules',
                'body' => 'This is a test ping.',
            ])
            ->assertRedirect(route('admin.index', ['tab' => 'push']))
            ->assertSessionHas('status', 'Test ping sent to 1 device.');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $mine->token
            && $request['message']['notification']['title'] === 'Spice Rules'
            && $request['message']['notification']['body'] === 'This is a test ping.');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $theirs->token);
    }

    public function test_the_admin_can_send_a_test_ping_to_everyone(): void
    {
        $this->enableFirebase();
        Cache::flush();

        $admin = $this->admin();
        $other = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $admin->id,
            'token' => str_repeat('a', 40),
        ]);
        DeviceToken::factory()->create([
            'user_id' => $other->id,
            'token' => str_repeat('b', 40),
        ]);

        $this->fakeFcm();

        $this->actingAs($admin)
            ->post(route('admin.push.send'), [
                'audience' => 'everyone',
                'title' => 'Board ping',
                'body' => 'Hello everyone.',
            ])
            ->assertRedirect(route('admin.index', ['tab' => 'push']))
            ->assertSessionHas('status', 'Test ping sent to 2 devices.');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === str_repeat('a', 40));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === str_repeat('b', 40));
    }

    public function test_sending_to_me_without_a_device_shows_an_error(): void
    {
        $this->enableFirebase();

        $this->actingAs($this->admin())
            ->post(route('admin.push.send'), [
                'audience' => 'me',
                'title' => 'Spice Rules',
                'body' => 'This is a test ping.',
            ])
            ->assertRedirect(route('admin.index', ['tab' => 'push']))
            ->assertSessionHasErrors(['push' => 'Enable notifications on your Profile first, then send a test ping.']);
    }

    public function test_a_missing_title_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.index'))
            ->post(route('admin.push.send'), [
                'audience' => 'me',
                'title' => '',
                'body' => 'This is a test ping.',
            ])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHasErrors('title');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'mikaelclayton@gmail.com',
        ]);
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
}
