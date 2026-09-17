<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStepAnnouncementsRequest;
use App\Models\StepAnnouncement;
use Illuminate\Http\JsonResponse;

class AdminStepAnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'steps' => StepAnnouncement::payload(),
        ]);
    }

    public function update(UpdateStepAnnouncementsRequest $request): JsonResponse
    {
        foreach ($request->validated('steps') as $stepData) {
            $destination = trim((string) ($stepData['destination'] ?? ''));

            StepAnnouncement::query()->updateOrCreate(
                ['step' => $stepData['step']],
                [
                    'enabled' => (bool) $stepData['enabled'],
                    'destination' => $destination === '' ? null : $destination,
                ],
            );
        }

        return response()->json([
            'message' => 'تم حفظ إعدادات النداء.',
            'steps' => StepAnnouncement::payload(),
        ]);
    }
}
