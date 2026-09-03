<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geoguessers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $links = DB::table('geoguesser_challenges')
            ->select('geoguesser_id', 'user_id')
            ->distinct()
            ->get();

        foreach ($links as $link) {
            DB::table('geoguessers')
                ->where('id', $link->geoguesser_id)
                ->whereNull('user_id')
                ->update(['user_id' => $link->user_id]);
        }

        Schema::table('geoguessers', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('geoguesser_challenges', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('geoguesser_id')->constrained()->cascadeOnDelete();
        });

        $profiles = DB::table('geoguessers')->select('id', 'user_id')->whereNotNull('user_id')->get();

        foreach ($profiles as $profile) {
            DB::table('geoguesser_challenges')
                ->where('geoguesser_id', $profile->id)
                ->update(['user_id' => $profile->user_id]);
        }

        Schema::table('geoguessers', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
