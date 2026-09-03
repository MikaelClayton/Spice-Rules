<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geoguessers', function (Blueprint $table) {
            $table->text('ncfa')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('geoguessers', function (Blueprint $table) {
            $table->json('ncfa')->nullable()->change();
        });
    }
};
