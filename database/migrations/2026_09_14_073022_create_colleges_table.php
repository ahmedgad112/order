<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['slug' => 'information_technology', 'name' => 'تكنولوجيا المعلومات'],
            ['slug' => 'railway_technology', 'name' => 'تكنولوجيا السكك الحديدية'],
            ['slug' => 'textile_technology', 'name' => 'تكنولوجيا تشغيل وصيانة الغزل والنسيج'],
            ['slug' => 'food_industry_technology', 'name' => 'تكنولوجيا الصناعات الغذائية'],
            ['slug' => 'agricultural_equipment', 'name' => 'تكنولوجيا الجرارات والمعدات الزراعية'],
            ['slug' => 'dental_laboratory', 'name' => 'تكنولوجيا معامل الأسنان'],
            ['slug' => 'pharmaceutical_production', 'name' => 'تكنولوجيا الإنتاج الدوائي'],
            ['slug' => 'health_information_management', 'name' => 'تكنولوجيا إدارة المعلومات الصحية'],
            ['slug' => 'health_care_technology', 'name' => 'تكنولوجيا الرعاية الصحية'],
            ['slug' => 'health_science_basic', 'name' => 'العلوم الصحية الأساسية'],
        ];

        DB::table('colleges')->insert(
            array_map(
                fn (array $row, int $index): array => [
                    'slug' => $row['slug'],
                    'name' => $row['name'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $rows,
                array_keys($rows),
            ),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colleges');
    }
};
