<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spirdle_words', function (Blueprint $table) {
            $table->id();
            $table->char('word', 5);
            $table->boolean('is_answer')->default(false);

            $table->unique('word');
            $table->index(['is_answer', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spirdle_words');
    }
};
