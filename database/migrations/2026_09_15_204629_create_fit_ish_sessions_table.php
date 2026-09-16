<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fit_ish_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fit_ish_studio_id')->nullable()->constrained('fit_ish_studios')->nullOnDelete();
            $table->foreignId('fit_ish_workout_id')->nullable()->constrained('fit_ish_workouts')->nullOnDelete();
            $table->date('class_date');
            $table->time('class_time');
            $table->timestamp('started_at')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('localized_date_time')->nullable();
            $table->unsignedSmallInteger('duration_in_minutes')->nullable();
            $table->unsignedInteger('tracked_duration_seconds')->nullable();
            $table->decimal('points', 8, 2)->nullable();
            $table->unsignedSmallInteger('average_heartrate')->nullable();
            $table->unsignedSmallInteger('max_heartrate')->nullable();
            $table->unsignedSmallInteger('estimated_calories')->nullable();
            $table->string('heartrate_method', 64)->nullable();
            $table->unsignedSmallInteger('max_hr_default')->nullable();
            $table->unsignedSmallInteger('max_hr_override')->nullable();
            $table->unsignedSmallInteger('max_hr_value')->nullable();
            $table->unsignedSmallInteger('resting_hr_default')->nullable();
            $table->unsignedSmallInteger('resting_hr_override')->nullable();
            $table->unsignedSmallInteger('resting_hr_value')->nullable();
            $table->string('graph_type', 64)->nullable();
            $table->timestamps();

            $table->index(['class_date', 'points']);
            $table->index(['user_id', 'class_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_ish_sessions');
    }
};
