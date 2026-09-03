<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('geoguessr_results');

        Schema::create('geoguessers', function (Blueprint $table) {
            $table->id();
            $table->string('username', 250)->nullable();
            $table->text('ncfa')->nullable();
            $table->integer('daily_challenge_progress')->nullable();
            $table->json('progress')->nullable();
            $table->integer('daily_challenge_streak')->nullable();
            $table->integer('daily_challenge_current_streak')->nullable();
            $table->boolean('is_active')->nullable()->default(true);
            $table->timestamps();
        });

        Schema::create('geoguesser_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geoguesser_id')->constrained('geoguessers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('attempted_at')->nullable();
            $table->string('challenge_token', 250)->nullable();
            $table->integer('total_score')->nullable();
            $table->string('geoguesser_guid', 250)->nullable();
            $table->integer('total_distance')->nullable();
            $table->integer('total_steps_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geoguesser_challenges');
        Schema::dropIfExists('geoguessers');

        Schema::create('geoguessr_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('played_on');
            $table->unsignedInteger('score');
            $table->timestamps();

            $table->unique(['user_id', 'played_on']);
        });
    }
};
