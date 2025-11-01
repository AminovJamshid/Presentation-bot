<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;

// Test route
Route::get('/test', function () {
    return response()->json(['message' => 'API ishlayapti!']);
});

// User route (Sanctum autentifikatsiya bilan)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ======== TELEGRAM ROUTES ========

// Webhook (Telegram dan xabarlar)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');

// Webhook sozlash
Route::get('/telegram/set-webhook', [TelegramWebhookController::class, 'setWebhook'])
    ->name('telegram.set-webhook');

// Webhook info
Route::get('/telegram/webhook-info', [TelegramWebhookController::class, 'getWebhookInfo'])
    ->name('telegram.webhook-info');
