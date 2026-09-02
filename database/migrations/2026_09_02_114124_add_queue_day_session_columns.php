<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_system_settings', function (Blueprint $table) {
            $table->timestamp('current_session_started_at')->nullable()->after('is_open');
            $table->timestamp('day_ended_at')->nullable()->after('last_reset_by');
            $table->foreignId('day_ended_by')->nullable()->after('day_ended_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->timestamp('session_started_at')->nullable()->after('ticket_number');
            $table->index(['session_started_at', 'ticket_number']);
        });

        $startOfToday = now()->startOfDay();

        DB::table('queue_system_settings')->update([
            'current_session_started_at' => $startOfToday,
        ]);

        DB::table('queue_tickets')->orderBy('id')->chunkById(100, function ($tickets): void {
            foreach ($tickets as $ticket) {
                DB::table('queue_tickets')->where('id', $ticket->id)->update([
                    'session_started_at' => Carbon::parse($ticket->created_at)->startOfDay(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropIndex(['session_started_at', 'ticket_number']);
            $table->dropColumn('session_started_at');
        });

        Schema::table('queue_system_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('day_ended_by');
            $table->dropColumn(['current_session_started_at', 'day_ended_at']);
        });
    }
};
