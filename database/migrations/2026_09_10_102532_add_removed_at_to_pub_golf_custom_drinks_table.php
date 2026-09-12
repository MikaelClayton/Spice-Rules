<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pub_golf_custom_drinks', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('pub_golf_custom_drinks', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
