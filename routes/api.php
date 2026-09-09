<?php

use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminSystemController;
use App\Http\Controllers\Api\AdminTicketController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicQueueController;
use App\Http\Controllers\Api\TellerQueueController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function (): void {
    Route::get('/queue-status', [PublicQueueController::class, 'queueStatus']);
    Route::post('/tickets', [PublicQueueController::class, 'issueTicket'])
        ->middleware('throttle:20,1');
    Route::post('/tickets/track', [PublicQueueController::class, 'trackTicket'])
        ->middleware('throttle:60,1');
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'changePassword'])
        ->middleware('throttle:6,1');

    Route::prefix('teller')->middleware('role:teller,manager,super_admin')->group(function (): void {
        Route::get('/queue-status', [TellerQueueController::class, 'queueStatus']);
        Route::get('/tickets', [TellerQueueController::class, 'tickets']);
        Route::get('/tickets/scan/{ticket:public_token}', [TellerQueueController::class, 'showScannedTicket']);
        Route::get('/tickets/{ticket}/document', [TellerQueueController::class, 'showDocument']);
        Route::get('/current-ticket', [TellerQueueController::class, 'currentTicket']);
        Route::post('/call-next', [TellerQueueController::class, 'callNext']);
        Route::post('/tickets/{ticket}/complete', [TellerQueueController::class, 'completeTicket']);
        Route::post('/tickets/{ticket}/cancel', [TellerQueueController::class, 'cancelTicket']);
        Route::post('/tickets/{ticket}/recall', [TellerQueueController::class, 'recallTicket']);
        Route::get('/absent-tickets', [TellerQueueController::class, 'absentTickets']);
        Route::post('/tickets/{ticket}/mark-absent', [TellerQueueController::class, 'markAbsent']);
        Route::post('/tickets/{ticket}/restore', [TellerQueueController::class, 'restoreTicket']);
        Route::post('/tickets/{ticket}/mark-entered', [TellerQueueController::class, 'markEntered']);
        Route::post('/tickets/{ticket}/mark-medical-checked', [TellerQueueController::class, 'markMedicalChecked']);
        Route::post('/tickets/{ticket}/mark-face-printed', [TellerQueueController::class, 'markFacePrinted']);
        Route::post('/tickets/{ticket}/mark-file-delivered', [TellerQueueController::class, 'markFileDelivered']);
    });

    Route::prefix('admin')->middleware('role:manager,super_admin')->group(function (): void {
        Route::get('/dashboard', [AdminReportController::class, 'dashboard']);
        Route::get('/system/status', [AdminSystemController::class, 'status']);
        Route::get('/reports/daily', [AdminReportController::class, 'dailyMetrics']);
        Route::get('/reports/teller-performance', [AdminReportController::class, 'tellerPerformance']);
        Route::get('/tellers', [AdminReportController::class, 'tellers']);
        Route::get('/tickets', [AdminTicketController::class, 'index']);
        Route::put('/tickets/{ticket}', [AdminTicketController::class, 'update']);
        Route::post('/tickets/{ticket}/mark-entered', [AdminTicketController::class, 'markEntered']);
        Route::put('/queue-lanes/{lane}/tellers', [AdminSystemController::class, 'updateLaneTellers']);
    });

    Route::prefix('admin')->middleware('role:super_admin')->group(function (): void {
        Route::post('/system/close', [AdminSystemController::class, 'close']);
        Route::post('/system/open', [AdminSystemController::class, 'open']);
        Route::post('/system/end-day', [AdminSystemController::class, 'endDay']);
        Route::post('/system/open-day', [AdminSystemController::class, 'openDay']);
        Route::put('/system/request-types', [AdminSystemController::class, 'updateRequestTypes']);
        Route::delete('/tickets/{ticket}', [AdminTicketController::class, 'destroy']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    });
});
