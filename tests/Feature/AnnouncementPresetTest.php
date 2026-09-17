<?php

namespace Tests\Feature;

use App\Models\AnnouncementPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementPresetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_announcement_presets(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/announcement-presets', [
            'label' => 'استراحة',
            'text' => 'برجاء الانتظار، سيتم استئناف العمل بعد قليل',
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'presets')
            ->assertJsonPath('presets.0.label', 'استراحة');

        $preset = AnnouncementPreset::query()->firstOrFail();

        $this->putJson('/api/admin/announcement-presets/'.$preset->id, [
            'label' => 'استراحة الغداء',
        ])
            ->assertOk()
            ->assertJsonPath('presets.0.label', 'استراحة الغداء');

        $this->getJson('/api/admin/announcement-presets')
            ->assertOk()
            ->assertJsonCount(1, 'presets');

        $this->deleteJson('/api/admin/announcement-presets/'.$preset->id)
            ->assertOk()
            ->assertJsonCount(0, 'presets');

        $this->assertDatabaseMissing('announcement_presets', ['id' => $preset->id]);
    }

    public function test_teller_cannot_manage_announcement_presets(): void
    {
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/admin/announcement-presets', [
            'label' => 'رسالة',
            'text' => 'نص تجريبي',
        ])->assertForbidden();
    }

    public function test_preset_requires_label_and_text(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/announcement-presets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label', 'text']);
    }
}
