<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pub_golf_custom_drinks', function (Blueprint $table) {
            $table->unsignedSmallInteger('calories')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('pub_golf_custom_drinks', function (Blueprint $table) {
            $table->dropColumn('calories');
        });
    }
};
