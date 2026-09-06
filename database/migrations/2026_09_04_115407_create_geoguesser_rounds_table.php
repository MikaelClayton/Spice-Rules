<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->string('game_token', 250)->nullable()->after('challenge_token');
            $table->string('map_name', 250)->nullable()->after('game_token');
        });

        Schema::create('geoguesser_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geoguesser_challenge_id')->constrained('geoguesser_challenges')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_number');
            $table->decimal('actual_lat', 10, 7)->nullable();
            $table->decimal('actual_lng', 10, 7)->nullable();
            $table->decimal('guess_lat', 10, 7)->nullable();
            $table->decimal('guess_lng', 10, 7)->nullable();
            $table->integer('score')->nullable();
            $table->decimal('percentage', 8, 2)->nullable();
            $table->unsignedInteger('time')->nullable();
            $table->unsignedInteger('steps_count')->nullable();
            $table->unsignedInteger('distance_in_meters')->nullable();
            $table->boolean('timed_out')->nullable();
            $table->boolean('timed_out_with_guess')->nullable();
            $table->boolean('skipped_round')->nullable();
            $table->decimal('heading', 10, 6)->nullable();
            $table->decimal('pitch', 10, 6)->nullable();
            $table->unsignedTinyInteger('zoom')->nullable();
            $table->text('pano_id')->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('guess_country_code', 8)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->unique(['geoguesser_challenge_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geoguesser_rounds');

        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->dropColumn(['game_token', 'map_name']);
        });
    }
};
