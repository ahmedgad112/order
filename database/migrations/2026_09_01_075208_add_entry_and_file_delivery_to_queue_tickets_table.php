<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->timestamp('entered_at')->nullable()->after('called_at');
            $table->timestamp('file_delivered_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropColumn(['entered_at', 'file_delivered_at']);
        });
    }
};
