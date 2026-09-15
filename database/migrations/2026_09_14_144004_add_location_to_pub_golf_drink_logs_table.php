<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pub_golf_drink_logs', function (Blueprint $table) {
            $table->string('location', 80)->nullable()->after('drink_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('pub_golf_drink_logs', function (Blueprint $table) {
            $table->dropColumn(['location', 'latitude', 'longitude']);
        });
    }
};
