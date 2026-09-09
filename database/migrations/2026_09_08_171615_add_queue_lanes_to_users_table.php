<?php

use App\Enums\QueueLane;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'queue_lanes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('queue_lanes')->nullable()->after('counter_name');
            });
        }

        $lanes = json_encode(QueueLane::values());

        DB::table('users')
            ->where('role', UserRole::Teller->value)
            ->whereNull('queue_lanes')
            ->update(['queue_lanes' => $lanes]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'queue_lanes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('queue_lanes');
            });
        }
    }
};
