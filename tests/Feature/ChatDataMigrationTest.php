<?php

namespace Tests\Feature;

use App\Models\PubGolfCrawl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_pub_golf_chat_rows_move_onto_chatable_tables(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $this->assertTrue(Schema::hasTable('pub_golf_chat_messages'));
        $this->assertFalse(Schema::hasTable('chat_messages'));

        $now = now();
        $messageId = DB::table('pub_golf_chat_messages')->insertGetId([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'body' => 'First round is on me',
            'photo_path' => 'pub-golf/chat/old.jpg',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('pub_golf_chat_mentions')->insert([
            'pub_golf_chat_message_id' => $messageId,
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('pub_golf_chat_reads')->insert([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'last_read_message_id' => $messageId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertDatabaseHas('chat_messages', [
            'id' => $messageId,
            'chatable_type' => 'pub_golf_crawl',
            'chatable_id' => $crawl->id,
            'user_id' => $user->id,
            'body' => 'First round is on me',
            'photo_path' => 'pub-golf/chat/old.jpg',
        ]);
        $this->assertDatabaseHas('chat_mentions', [
            'chat_message_id' => $messageId,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('chat_reads', [
            'chatable_type' => 'pub_golf_crawl',
            'chatable_id' => $crawl->id,
            'user_id' => $user->id,
            'last_read_message_id' => $messageId,
        ]);
        $this->assertFalse(Schema::hasTable('pub_golf_chat_messages'));
        $this->assertFalse(Schema::hasTable('pub_golf_chat_mentions'));
        $this->assertFalse(Schema::hasTable('pub_golf_chat_reads'));
    }
}
