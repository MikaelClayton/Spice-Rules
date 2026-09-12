<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pub_golf_custom_drinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->timestamps();
        });

        DB::table('pub_golf_drink_logs')
            ->whereIn('drink', ['water', 'stoney', 'coke', 'sprite', 'red_bull'])
            ->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('pub_golf_custom_drinks');
    }
};
