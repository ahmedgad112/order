<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('queue_tickets', 'request_type')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->string('request_type')->nullable()->after('national_id');
            });
        }

        if (! Schema::hasColumn('queue_system_settings', 'enabled_request_types')) {
            Schema::table('queue_system_settings', function (Blueprint $table) {
                $table->json('enabled_request_types')->nullable()->after('is_open');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('queue_tickets', 'request_type')) {
            Schema::table('queue_tickets', function (Blueprint $table) {
                $table->dropColumn('request_type');
            });
        }

        if (Schema::hasColumn('queue_system_settings', 'enabled_request_types')) {
            Schema::table('queue_system_settings', function (Blueprint $table) {
                $table->dropColumn('enabled_request_types');
            });
        }
    }
};
