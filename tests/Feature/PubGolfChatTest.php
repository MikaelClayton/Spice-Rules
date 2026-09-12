<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\DeviceToken;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConfiguresFirebase;
use Tests\TestCase;

class PubGolfChatTest extends TestCase
{
    use ConfiguresFirebase;
    use RefreshDatabase;

    public function test_players_can_send_a_chat_message_on_an_open_crawl(): void
    {
        [$host, $friend, $crawl] = $this->openCrawl();

        $this->actingAs($friend)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => 'Next round is on me',
            ])
            ->assertRedirect(route('pub-golf.show', $crawl));

        $this->assertDatabaseHas('chat_messages', [
            'chatable_type' => 'pub_golf_crawl',
            'chatable_id' => $crawl->id,
            'user_id' => $friend->id,
            'body' => 'Next round is on me',
        ]);

        $this->actingAs($host)
            ->getJson(route('pub-golf.chat.index', $crawl))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('mentions', 0)
            ->assertJsonPath('messages.0.body', 'Next round is on me')
            ->assertJsonPath('messages.0.is_you', false);
    }

    public function test_a_blank_chat_message_is_rejected(): void
    {
        [, $friend, $crawl] = $this->openCrawl();

        $this->actingAs($friend)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => '   ',
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['body' => 'Write a message or add a photo.']);

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_at_mentions_create_a_separate_unread_count(): void
    {
        [$host, $friend, $crawl] = $this->openCrawl('Ted Smith', 'Sam Fine');

        $this->actingAs($friend)
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => 'Where are you @ted',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('chat_mentions', [
            'user_id' => $host->id,
        ]);

        $this->actingAs($host)
            ->getJson(route('pub-golf.chat.index', $crawl))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('mentions', 1);

        $this->actingAs($host)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('Crawl chat')
            ->assertSee('>1<', false)
            ->assertSee('>@1<', false);

        $this->actingAs($friend)
            ->getJson(route('pub-golf.chat.index', $crawl))
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('mentions', 0);
    }

    public function test_opening_chat_clears_unread_counts(): void
    {
        [$host, $friend, $crawl] = $this->openCrawl('Ted Smith', 'Sam Fine');
        $message = ChatMessage::factory()->for($crawl, 'chatable')->create([
            'user_id' => $friend->id,
            'body' => 'Come back @ted',
        ]);
        $message->mentions()->create(['user_id' => $host->id]);

        $this->actingAs($host)
            ->postJson(route('pub-golf.chat.read', $crawl), [
                'last_read_message_id' => $message->id,
            ])
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('mentions', 0);

        $this->assertDatabaseHas('chat_reads', [
            'chatable_type' => 'pub_golf_crawl',
            'chatable_id' => $crawl->id,
            'user_id' => $host->id,
            'last_read_message_id' => $message->id,
        ]);
    }

    public function test_players_can_send_a_compressed_chat_photo(): void
    {
        Storage::fake('public');
        [, $friend, $crawl] = $this->openCrawl();
        $photo = UploadedFile::fake()->image('round.png', 2000, 1000);

        $this->actingAs($friend)
            ->post(route('pub-golf.chat.store', $crawl), [
                'photo' => $photo,
            ])
            ->assertRedirect();

        $message = ChatMessage::query()->first();

        $this->assertNotNull($message?->photo_path);
        $this->assertStringStartsWith('chat/', (string) $message->photo_path);
        Storage::disk('public')->assertExists($message->photo_path);

        $size = getimagesizefromstring((string) Storage::disk('public')->get($message->photo_path));

        $this->assertNotFalse($size);
        $this->assertSame(1280, $size[0]);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
    }

    public function test_heic_chat_photos_are_rejected(): void
    {
        Storage::fake('public');
        [, $friend, $crawl] = $this->openCrawl();

        $this->actingAs($friend)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.chat.store', $crawl), [
                'photo' => UploadedFile::fake()->create('round.heic', 200, 'image/heic'),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['photo' => 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.']);

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_chat_messages_escape_html(): void
    {
        [$host, $friend, $crawl] = $this->openCrawl();

        $this->actingAs($friend)
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => '<script>alert("xss")</script>',
            ]);

        $this->actingAs($host)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('<script>alert("xss")</script>')
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_outsiders_cannot_read_or_send_chat(): void
    {
        [, , $crawl] = $this->openCrawl();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson(route('pub-golf.chat.index', $crawl))
            ->assertRedirect(route('pub-golf.index'));

        $this->actingAs($outsider)
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => 'I should not be here',
            ])
            ->assertRedirect(route('pub-golf.index'));

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_wrapped_crawls_reject_new_chat_messages(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->ended()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.recap.show', $crawl))
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => 'One more for the road',
            ])
            ->assertRedirect(route('pub-golf.recap.show', $crawl))
            ->assertSessionHasErrors(['body' => 'That crawl has already wrapped up.']);
    }

    public function test_people_who_called_it_can_still_chat_while_the_crawl_is_open(): void
    {
        [$host, $friend, $crawl] = $this->openCrawl();

        $this->actingAs($host)->post(route('pub-golf.leave.store', $crawl));

        $this->actingAs($host)
            ->from(route('pub-golf.recap.show', $crawl))
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => 'I am grabbing a water',
            ])
            ->assertRedirect(route('pub-golf.recap.show', $crawl));

        $this->assertDatabaseHas('chat_messages', [
            'chatable_type' => 'pub_golf_crawl',
            'chatable_id' => $crawl->id,
            'user_id' => $host->id,
            'body' => 'I am grabbing a water',
        ]);

        $this->actingAs($host)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertOk()
            ->assertSee('Crawl chat')
            ->assertSee('I am grabbing a water');
    }

    public function test_chat_notifies_the_rest_of_the_crawl_and_uses_a_mention_copy(): void
    {
        $this->enableFirebase();
        $this->fakeFcm();

        [$host, $friend, $crawl] = $this->openCrawl('Ted Smith', 'Sam Fine');
        $bystander = User::factory()->create(['name' => 'Pat']);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $bystander->id,
        ]);
        $hostToken = DeviceToken::factory()->create([
            'user_id' => $host->id,
            'token' => str_repeat('h', 40),
        ]);
        $friendToken = DeviceToken::factory()->create([
            'user_id' => $friend->id,
            'token' => str_repeat('f', 40),
        ]);
        $bystanderToken = DeviceToken::factory()->create([
            'user_id' => $bystander->id,
            'token' => str_repeat('b', 40),
        ]);

        $this->actingAs($friend)
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => 'Where are you @ted',
            ])
            ->assertRedirect();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
            && $request['message']['token'] === $hostToken->token
            && $request['message']['notification']['title'] === $crawl->name
            && $request['message']['notification']['body'] === 'Sam Fine mentioned you');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/spice-rules-test/messages:send'
            && $request['message']['token'] === $bystanderToken->token
            && $request['message']['notification']['body'] === 'Sam Fine: Where are you @ted');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === $friendToken->token);
    }

    public function test_chat_does_not_send_push_when_firebase_is_not_configured(): void
    {
        Http::preventStrayRequests();

        [, $friend, $crawl] = $this->openCrawl();
        DeviceToken::factory()->create(['user_id' => $crawl->user_id]);

        $this->actingAs($friend)
            ->post(route('pub-golf.chat.store', $crawl), [
                'body' => 'Hello',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_guests_cannot_use_crawl_chat(): void
    {
        $this->post(route('pub-golf.chat.store', 1), ['body' => 'Hi'])->assertRedirect(route('login'));
    }

    /**
     * @return array{0: User, 1: User, 2: PubGolfCrawl}
     */
    private function openCrawl(string $hostName = 'Host', string $friendName = 'Friend'): array
    {
        $host = User::factory()->create(['name' => $hostName]);
        $friend = User::factory()->create(['name' => $friendName]);
        $crawl = PubGolfCrawl::factory()->create([
            'user_id' => $host->id,
            'name' => 'Friday in Obs',
        ]);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
        ]);

        return [$host, $friend, $crawl];
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
