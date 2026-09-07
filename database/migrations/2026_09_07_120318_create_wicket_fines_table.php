<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wicket_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wicket_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->string('type');
            $table->unsignedTinyInteger('sips_owed')->default(0);
            $table->unsignedTinyInteger('sips_completed')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['wicket_group_id', 'issued_to_user_id', 'completed_at'],
                'wicket_fines_outstanding_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wicket_fines');
    }
};
