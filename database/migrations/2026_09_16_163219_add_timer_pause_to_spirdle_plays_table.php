<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spirdle_plays', function (Blueprint $table) {
            $table->unsignedInteger('accumulated_ms')->default(0)->after('duration_ms');
            $table->timestamp('running_since')->nullable()->after('started_at');
        });

        DB::table('spirdle_plays')
            ->whereNull('finished_at')
            ->whereNull('running_since')
            ->update(['running_since' => DB::raw('started_at')]);
    }

    public function down(): void
    {
        Schema::table('spirdle_plays', function (Blueprint $table) {
            $table->dropColumn(['accumulated_ms', 'running_since']);
        });
    }
};
