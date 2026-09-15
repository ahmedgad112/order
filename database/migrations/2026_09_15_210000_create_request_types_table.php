<?php

use App\Enums\ProcessStep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('code_prefix', 4)->unique();
            $table->string('college_mode')->default('text');
            $table->string('college_label')->nullable();
            $table->boolean('requires_completion_service')->default(false);
            $table->json('completion_services')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (DB::table('request_types')->count() === 0) {
            DB::table('request_types')->insert([
                [
                    'slug' => 'nomination_card',
                    'label' => 'حاصل على بطاقة ترشيح',
                    'code_prefix' => 'OT',
                    'college_mode' => 'select',
                    'college_label' => 'الكلية الواردة في بطاقة الترشيح',
                    'requires_completion_service' => false,
                    'completion_services' => null,
                    'enabled' => true,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'slug' => 'direct_application',
                    'label' => 'تقديم مباشر',
                    'code_prefix' => 'OD',
                    'college_mode' => 'text',
                    'college_label' => 'الكلية المراد الالتحاق بها',
                    'requires_completion_service' => false,
                    'completion_services' => null,
                    'enabled' => true,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'slug' => 'transfer',
                    'label' => 'تحويل (مناظر / غير مناظر)',
                    'code_prefix' => 'OB',
                    'college_mode' => 'text',
                    'college_label' => 'الكلية المراد الالتحاق بها',
                    'requires_completion_service' => false,
                    'completion_services' => null,
                    'enabled' => true,
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'slug' => 'document_completion',
                    'label' => 'استكمال أوراق',
                    'code_prefix' => 'OF',
                    'college_mode' => 'text',
                    'college_label' => 'الكلية',
                    'requires_completion_service' => true,
                    'completion_services' => json_encode(ProcessStep::admissionCompletionValues()),
                    'enabled' => true,
                    'sort_order' => 4,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('request_types');
    }
};
