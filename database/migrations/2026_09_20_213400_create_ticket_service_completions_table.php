<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_service_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_ticket_id')->constrained('queue_tickets')->cascadeOnDelete();
            $table->foreignId('process_service_id')->constrained('process_services')->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['queue_ticket_id', 'process_service_id'], 'tsc_ticket_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_service_completions');
    }
};
