<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->fullNameAllowsNull()) {
            return;
        }

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->string('full_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! $this->fullNameAllowsNull()) {
            return;
        }

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->string('full_name')->nullable(false)->change();
        });
    }

    private function fullNameAllowsNull(): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $column = collect(DB::select('PRAGMA table_info(queue_tickets)'))
                ->first(fn (object $column): bool => $column->name === 'full_name');

            return $column !== null && (int) $column->notnull === 0;
        }

        $column = collect(Schema::getColumns('queue_tickets'))
            ->firstWhere('name', 'full_name');

        return (bool) ($column['nullable'] ?? false);
    }
};
