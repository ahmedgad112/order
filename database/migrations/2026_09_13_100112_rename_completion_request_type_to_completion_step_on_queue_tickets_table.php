<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('queue_tickets', 'completion_request_type')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->dropColumn('completion_request_type');
            });
        }

        if (! Schema::hasColumn('queue_tickets', 'completion_step')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->string('completion_step')->nullable()->after('request_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('queue_tickets', 'completion_step')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->dropColumn('completion_step');
            });
        }

        if (! Schema::hasColumn('queue_tickets', 'completion_request_type')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->string('completion_request_type')->nullable()->after('request_type');
            });
        }
    }
};
