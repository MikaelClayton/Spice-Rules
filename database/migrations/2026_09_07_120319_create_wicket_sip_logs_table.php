<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wicket_sip_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wicket_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sips');
            $table->timestamps();

            $table->index(['wicket_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wicket_sip_logs');
    }
};
