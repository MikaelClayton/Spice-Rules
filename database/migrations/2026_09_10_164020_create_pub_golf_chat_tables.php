<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('pub_golf_chat_reads');
        Schema::dropIfExists('pub_golf_chat_mentions');
        Schema::dropIfExists('pub_golf_chat_messages');
    }
};
