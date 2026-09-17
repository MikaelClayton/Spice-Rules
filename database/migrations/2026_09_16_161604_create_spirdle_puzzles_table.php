<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spirdle_puzzles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spirdle_word_id')->constrained()->restrictOnDelete();
            $table->date('play_date');
            $table->timestamps();

            $table->unique('play_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spirdle_puzzles');
    }
};
