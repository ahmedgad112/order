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
        Schema::table('request_types', function (Blueprint $table): void {
            $table->string('counter_name', 100)->nullable()->after('college_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table): void {
            $table->dropColumn('counter_name');
        });
    }
};
