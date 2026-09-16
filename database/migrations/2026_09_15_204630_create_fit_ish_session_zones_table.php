<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fit_ish_session_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fit_ish_session_id')->constrained('fit_ish_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('zone_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color_hex', 16)->nullable();
            $table->decimal('min_percentage', 5, 1)->nullable();
            $table->decimal('max_percentage', 5, 1)->nullable();
            $table->unsignedSmallInteger('min_bpm')->nullable();
            $table->unsignedSmallInteger('max_bpm')->nullable();
            $table->string('bpm_label')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('duration_label', 32)->nullable();
            $table->decimal('percentage_value', 5, 1)->nullable();
            $table->string('percentage_label', 16)->nullable();
            $table->timestamps();

            $table->unique(['fit_ish_session_id', 'zone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_ish_session_zones');
    }
};
