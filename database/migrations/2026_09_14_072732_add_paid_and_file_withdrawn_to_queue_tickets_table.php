<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_tickets', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('entered_at');
            }

            if (! Schema::hasColumn('queue_tickets', 'file_withdrawn_at')) {
                $table->timestamp('file_withdrawn_at')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['file_withdrawn_at', 'paid_at'],
                fn (string $column): bool => Schema::hasColumn('queue_tickets', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
