<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->unique(['geoguesser_id', 'challenge_token']);
        });
    }

    public function down(): void
    {
        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->dropUnique(['geoguesser_id', 'challenge_token']);
        });
    }
};
