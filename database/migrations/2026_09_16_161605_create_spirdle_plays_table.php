<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spirdle_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spirdle_puzzle_id')->constrained()->cascadeOnDelete();
            $table->json('guesses');
            $table->unsignedTinyInteger('guess_count')->default(0);
            $table->unsignedSmallInteger('invalid_word_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('won')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'spirdle_puzzle_id']);
            $table->index(['spirdle_puzzle_id', 'finished_at'], 'spirdle_plays_finished_index');
            $table->index(
                ['spirdle_puzzle_id', 'won', 'guess_count', 'duration_ms'],
                'spirdle_plays_leaderboard_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spirdle_plays');
    }
};
