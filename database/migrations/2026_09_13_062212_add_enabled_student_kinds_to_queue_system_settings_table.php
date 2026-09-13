<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('queue_system_settings', 'enabled_student_kinds')) {
            Schema::table('queue_system_settings', function (Blueprint $table) {
                $table->json('enabled_student_kinds')->nullable()->after('enabled_request_types');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('queue_system_settings', 'enabled_student_kinds')) {
            Schema::table('queue_system_settings', function (Blueprint $table) {
                $table->dropColumn('enabled_student_kinds');
            });
        }
    }
};
