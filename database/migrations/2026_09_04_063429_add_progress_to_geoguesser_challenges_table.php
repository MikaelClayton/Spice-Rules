<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->json('progress')->nullable()->after('total_steps_count');
        });
    }

    public function down(): void
    {
        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->dropColumn('progress');
        });
    }
};
