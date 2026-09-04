<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_api_calls', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('method', 16);
            $table->string('url');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('succeeded');
            $table->unsignedInteger('duration_ms');
            $table->text('error_message')->nullable();
            $table->foreignId('cron_run_id')->nullable()->constrained('cron_runs')->nullOnDelete();
            $table->foreignId('geoguesser_id')->nullable()->constrained('geoguessers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_api_calls');
    }
};
