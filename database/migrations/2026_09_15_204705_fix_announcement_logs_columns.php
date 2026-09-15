<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcement_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('announcement_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('announcement_logs', 'text')) {
                $table->text('text')->after('user_id');
            }
            if (! Schema::hasColumn('announcement_logs', 'voice')) {
                $table->string('voice', 50)->default('')->after('text');
            }
            if (! Schema::hasColumn('announcement_logs', 'rate')) {
                $table->string('rate', 10)->default('')->after('voice');
            }
            if (! Schema::hasColumn('announcement_logs', 'audio_filename')) {
                $table->string('audio_filename')->nullable()->after('rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcement_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['text', 'voice', 'rate', 'audio_filename']);
        });
    }
};
