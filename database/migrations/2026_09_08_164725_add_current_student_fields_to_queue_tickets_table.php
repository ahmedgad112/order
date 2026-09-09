<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->string('national_id', 14)->nullable()->change();
            $table->string('order_number')->nullable()->change();
        });

        Schema::table('queue_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_tickets', 'student_kind')) {
                $table->string('student_kind')->default('new_student')->after('full_name');
            }

            if (! Schema::hasColumn('queue_tickets', 'department')) {
                $table->string('department')->nullable()->after('college');
            }

            if (! Schema::hasColumn('queue_tickets', 'seat_number')) {
                $table->string('seat_number', 7)->nullable()->after('order_number');
            }

            if (! Schema::hasColumn('queue_tickets', 'document_kind')) {
                $table->string('document_kind')->nullable()->after('seat_number');
            }

            if (! Schema::hasColumn('queue_tickets', 'document_path')) {
                $table->string('document_path')->nullable()->after('document_kind');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['student_kind', 'department', 'seat_number', 'document_kind', 'document_path'],
            fn (string $column): bool => Schema::hasColumn('queue_tickets', $column),
        ));

        if ($columns !== []) {
            Schema::table('queue_tickets', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->string('national_id', 14)->nullable(false)->change();
            $table->string('order_number')->nullable(false)->change();
        });
    }
};
