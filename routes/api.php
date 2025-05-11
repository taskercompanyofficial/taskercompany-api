<?php

use App\Http\Controllers\ImageUploaderController;
use App\Http\Controllers\StoreUserSpecificController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WhatsappWebhookController;
use App\Http\Controllers\SendMessageTechnicianController;



use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\Staff\Authenticated\StaffController;
Route::post('/send-crm-notification', [NotificationController::class, 'sendNotification']);
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/health-check', [HealthCheckController::class, 'healthCheck']);
Route::post('/whatsapp/recieved-new', function (Request $request) {
    // Predefined token for verification
    $verifyToken = 'asdasdqweqweasdasdqweqweqweasdasdasdqweqwe';

    // Check if the token from the request matches the predefined token
    if ($request->input('verify_token') === $verifyToken) {
        // If the token is valid, return success
        return response()->json(['message' => 'Webhook verified successfully'], 200);
    }

    // If token is invalid, return an error
    return response()->json(['message' => 'Invalid token'], 400);
});
Route::post('/whatsapp/callback/response', [\App\Http\Controllers\WhatsappWebhookController::class, 'callback']);
Route::post('/assigned-to-technician', [\App\Http\Controllers\SendMessageTechnicianController::class, 'post']);
Route::post('/download/files', [ImageUploaderController::class, 'downloadFile']);

Route::middleware(['auth:sanctum'])->post('/upload-image', [ImageUploaderController::class, 'store']);

Route::middleware(['auth:sanctum'])->post('/save-push-token', [StoreUserSpecificController::class, 'store']);

Route::middleware(['auth:sanctum'])->post('/send-notification', [StoreUserSpecificController::class, 'sendNotification']);

Route::middleware(['auth:sanctum'])->post('/update-profile-image', [StaffController::class, 'updateProfileImage']);
Route::middleware(['auth:sanctum'])->post('/update-profile-image', [StaffController::class, 'updateProfileImage']);

require __DIR__ . '/auth.php';
require __DIR__ . '/worker-auth.php';
require __DIR__ . '/application-routes.php';
require __DIR__ . '/crm-routes.php';
