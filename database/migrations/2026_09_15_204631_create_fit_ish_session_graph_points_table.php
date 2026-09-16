<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fit_ish_session_graph_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fit_ish_session_id')->constrained('fit_ish_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('minute');
            $table->string('type', 32);
            $table->unsignedSmallInteger('bpm_min')->nullable();
            $table->unsignedSmallInteger('bpm_max')->nullable();
            $table->timestamps();

            $table->unique(['fit_ish_session_id', 'minute']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_ish_session_graph_points');
    }
};
