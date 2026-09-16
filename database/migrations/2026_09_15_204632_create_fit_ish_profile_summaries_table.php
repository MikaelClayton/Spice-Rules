<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fit_ish_profile_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('timeframe_key', 32);
            $table->unsignedTinyInteger('timeframe_id')->nullable();
            $table->string('timeframe_name');
            $table->unsignedSmallInteger('number_of_days')->nullable();
            $table->unsignedInteger('session_count')->default(0);
            $table->decimal('average_points', 8, 2)->nullable();
            $table->unsignedInteger('average_calories')->nullable();
            $table->decimal('max_points', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'timeframe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_ish_profile_summaries');
    }
};
