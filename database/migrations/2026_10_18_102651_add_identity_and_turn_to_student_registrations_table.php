<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->string('national_id', 14)->unique()->after('academic_number');
            $table->string('student_group', 1)->after('section_number');
            $table->date('service_date')->after('student_group');
            $table->unsignedInteger('turn_number')->after('service_date');
            $table->string('status')->default('waiting')->after('turn_number');
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->unique(['service_date', 'turn_number']);
            $table->index(['status', 'service_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropUnique(['service_date', 'turn_number']);
            $table->dropIndex(['status', 'service_date']);
            $table->dropUnique(['national_id']);
            $table->dropColumn([
                'national_id',
                'student_group',
                'service_date',
                'turn_number',
                'status',
                'completed_at',
            ]);
        });
    }
};
