<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pub_golf_crawls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('join_code', 6)->unique();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('ended_at');
        });

        Schema::create('pub_golf_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pub_golf_crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['pub_golf_crawl_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('pub_golf_drink_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pub_golf_crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('drink');
            $table->timestamps();

            $table->index(['pub_golf_crawl_id', 'created_at']);
            $table->index(['pub_golf_crawl_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pub_golf_drink_logs');
        Schema::dropIfExists('pub_golf_participants');
        Schema::dropIfExists('pub_golf_crawls');
    }
};
