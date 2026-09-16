<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fit_ish_user_id', 32)->nullable()->unique()->after('allow_pub_golf_location');
            $table->string('fit_ish_serial', 64)->nullable()->after('fit_ish_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['fit_ish_user_id']);
            $table->dropColumn(['fit_ish_user_id', 'fit_ish_serial']);
        });
    }
};
