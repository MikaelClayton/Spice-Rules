<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fit_ish_studio_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fit_ish_studio_id')->constrained('fit_ish_studios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fit_ish_studio_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_ish_studio_user');
    }
};
