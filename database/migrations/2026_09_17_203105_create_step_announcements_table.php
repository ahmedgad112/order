<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('step')->unique();
            $table->boolean('enabled')->default(true);
            $table->string('destination', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_announcements');
    }
};
