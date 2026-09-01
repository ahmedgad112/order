<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->timestamp('medical_checked_at')->nullable()->after('entered_at');
            $table->timestamp('face_printed_at')->nullable()->after('medical_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropColumn(['medical_checked_at', 'face_printed_at']);
        });
    }
};
