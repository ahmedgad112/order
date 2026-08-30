<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ticket_number');
            $table->string('full_name');
            $table->string('national_id', 14);
            $table->string('order_number');
            $table->string('status')->default('waiting');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['national_id', 'created_at']);
            $table->index(['order_number', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
