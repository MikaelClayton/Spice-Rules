<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createChatTables();
        $this->copyFromPubGolfChatTables();
        $this->dropPubGolfChatTables();
    }

    public function down(): void
    {
        $this->createPubGolfChatTables();
        $this->copyToPubGolfChatTables();
        $this->dropChatTables();
    }

    private function createChatTables(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chatable_type');
            $table->unsignedBigInteger('chatable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index(['chatable_type', 'chatable_id', 'id']);
        });

        Schema::create('chat_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id']);
        });

        Schema::create('chat_reads', function (Blueprint $table) {
            $table->id();
            $table->string('chatable_type');
            $table->unsignedBigInteger('chatable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['chatable_type', 'chatable_id', 'user_id']);
        });
    }

    private function createPubGolfChatTables(): void
    {
        Schema::create('pub_golf_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pub_golf_crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index(['pub_golf_crawl_id', 'id']);
        });

        Schema::create('pub_golf_chat_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pub_golf_chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pub_golf_chat_message_id', 'user_id']);
        });

        Schema::create('pub_golf_chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pub_golf_crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['pub_golf_crawl_id', 'user_id']);
        });
    }

    private function copyFromPubGolfChatTables(): void
    {
        if (! Schema::hasTable('pub_golf_chat_messages')) {
            return;
        }

        foreach (DB::table('pub_golf_chat_messages')->orderBy('id')->get() as $message) {
            DB::table('chat_messages')->insert([
                'id' => $message->id,
                'chatable_type' => 'pub_golf_crawl',
                'chatable_id' => $message->pub_golf_crawl_id,
                'user_id' => $message->user_id,
                'body' => $message->body,
                'photo_path' => $message->photo_path,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ]);
        }

        if (Schema::hasTable('pub_golf_chat_mentions')) {
            foreach (DB::table('pub_golf_chat_mentions')->orderBy('id')->get() as $mention) {
                DB::table('chat_mentions')->insert([
                    'id' => $mention->id,
                    'chat_message_id' => $mention->pub_golf_chat_message_id,
                    'user_id' => $mention->user_id,
                    'created_at' => $mention->created_at,
                    'updated_at' => $mention->updated_at,
                ]);
            }
        }

        if (Schema::hasTable('pub_golf_chat_reads')) {
            foreach (DB::table('pub_golf_chat_reads')->orderBy('id')->get() as $read) {
                DB::table('chat_reads')->insert([
                    'id' => $read->id,
                    'chatable_type' => 'pub_golf_crawl',
                    'chatable_id' => $read->pub_golf_crawl_id,
                    'user_id' => $read->user_id,
                    'last_read_message_id' => $read->last_read_message_id,
                    'created_at' => $read->created_at,
                    'updated_at' => $read->updated_at,
                ]);
            }
        }
    }

    private function copyToPubGolfChatTables(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        foreach (DB::table('chat_messages')->where('chatable_type', 'pub_golf_crawl')->orderBy('id')->get() as $message) {
            DB::table('pub_golf_chat_messages')->insert([
                'id' => $message->id,
                'pub_golf_crawl_id' => $message->chatable_id,
                'user_id' => $message->user_id,
                'body' => $message->body,
                'photo_path' => $message->photo_path,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ]);
        }

        $copiedMessageIds = DB::table('pub_golf_chat_messages')->pluck('id');

        if (Schema::hasTable('chat_mentions') && $copiedMessageIds->isNotEmpty()) {
            foreach (DB::table('chat_mentions')->whereIn('chat_message_id', $copiedMessageIds)->orderBy('id')->get() as $mention) {
                DB::table('pub_golf_chat_mentions')->insert([
                    'id' => $mention->id,
                    'pub_golf_chat_message_id' => $mention->chat_message_id,
                    'user_id' => $mention->user_id,
                    'created_at' => $mention->created_at,
                    'updated_at' => $mention->updated_at,
                ]);
            }
        }

        if (Schema::hasTable('chat_reads')) {
            foreach (DB::table('chat_reads')->where('chatable_type', 'pub_golf_crawl')->orderBy('id')->get() as $read) {
                DB::table('pub_golf_chat_reads')->insert([
                    'id' => $read->id,
                    'pub_golf_crawl_id' => $read->chatable_id,
                    'user_id' => $read->user_id,
                    'last_read_message_id' => $read->last_read_message_id,
                    'created_at' => $read->created_at,
                    'updated_at' => $read->updated_at,
                ]);
            }
        }
    }

    private function dropChatTables(): void
    {
        Schema::dropIfExists('chat_reads');
        Schema::dropIfExists('chat_mentions');
        Schema::dropIfExists('chat_messages');
    }

    private function dropPubGolfChatTables(): void
    {
        Schema::dropIfExists('pub_golf_chat_reads');
        Schema::dropIfExists('pub_golf_chat_mentions');
        Schema::dropIfExists('pub_golf_chat_messages');
    }
};
