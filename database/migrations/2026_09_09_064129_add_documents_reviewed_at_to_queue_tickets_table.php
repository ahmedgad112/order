<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_tickets', 'documents_reviewed_at')) {
                $table->timestamp('documents_reviewed_at')->nullable()->after('entered_at');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('queue_tickets', 'documents_reviewed_at')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->dropColumn('documents_reviewed_at');
            });
        }
    }
};
