<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementPresetRequest;
use App\Http\Requests\UpdateAnnouncementPresetRequest;
use App\Models\AnnouncementPreset;
use Illuminate\Http\JsonResponse;

class AdminAnnouncementPresetController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'presets' => $this->presetsPayload(),
        ]);
    }

    public function store(StoreAnnouncementPresetRequest $request): JsonResponse
    {
        AnnouncementPreset::query()->create($request->validated());

        return response()->json([
            'message' => 'تم حفظ الرسالة الجاهزة.',
            'presets' => $this->presetsPayload(),
        ], 201);
    }

    public function update(UpdateAnnouncementPresetRequest $request, AnnouncementPreset $announcementPreset): JsonResponse
    {
        $announcementPreset->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الرسالة الجاهزة.',
            'presets' => $this->presetsPayload(),
        ]);
    }

    public function destroy(AnnouncementPreset $announcementPreset): JsonResponse
    {
        $announcementPreset->delete();

        return response()->json([
            'message' => 'تم حذف الرسالة الجاهزة.',
            'presets' => $this->presetsPayload(),
        ]);
    }

    /**
     * @return list<array{id: int, label: string, text: string}>
     */
    private function presetsPayload(): array
    {
        return AnnouncementPreset::query()
            ->orderBy('id')
            ->get(['id', 'label', 'text'])
            ->all();
    }
}
