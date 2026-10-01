<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spirdle_practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spirdle_word_id')->constrained()->restrictOnDelete();
            $table->json('guesses');
            $table->unsignedTinyInteger('guess_count')->default(0);
            $table->unsignedSmallInteger('invalid_word_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('accumulated_ms')->default(0);
            $table->boolean('won')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('running_since')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spirdle_practices');
    }
};
