<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class FirebaseMessagingServiceWorkerTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_it_returns_404_when_firebase_is_not_configured(): void
    {
        $this->get(route('push.service-worker'))->assertNotFound();
    }

    public function test_it_returns_the_messaging_worker_when_firebase_is_configured(): void
    {
        $this->enableFirebase();

        $this->get(route('push.service-worker'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertSee("importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-app-compat.js')", false)
            ->assertSee('spice-rules-test', false)
            ->assertSee('firebase.messaging();', false);
    }

    public function test_profile_shows_enable_notifications_when_firebase_is_configured(): void
    {
        $this->enableFirebase();

        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Enable notifications')
            ->assertSee('On iPhone, add Spice Rules to your Home Screen');
    }
}
