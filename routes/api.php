<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;

use App\Models\Presentation;
use App\Services\AI\ContentGenerator;

use App\Services\AI\ImageFetcher;

use App\Services\Generator\PowerPointGenerator;

Route::get('/test-ai/{presentation}', function (Presentation $presentation) {
    try {
        $generator = new ContentGenerator();
        $content = $generator->generatePresentationContent($presentation);

        return response()->json([
            'success' => true,
            'presentation_id' => $presentation->id,
            'topic' => $presentation->topic,
            'slides_generated' => count($content),
            'content' => $content
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

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


Route::get('/test-image/{query}', function ($query) {
    try {
        $fetcher = new ImageFetcher();
        $result = $fetcher->fetchImage($query, 1);

        return response()->json([
            'success' => $result['success'],
            'path' => $result['path'],
            'source' => $result['source'],
            'photographer' => $result['photographer'] ?? null,
            'full_url' => $result['success'] ? asset('storage/' . $result['path']) : null,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::get('/test-full-generation/{presentation}', function (Presentation $presentation) {
    try {
        // 1. AI kontent
        $contentGen = new ContentGenerator();
        $slides = $contentGen->generatePresentationContent($presentation);

        // 2. Rasmlar
        $imageFetcher = new ImageFetcher();
        $images = [];
        foreach ($slides as $slide) {
            $images[$slide['slide_number']] = $imageFetcher->fetchImage(
                $slide['image_query'],
                $slide['slide_number']
            );
        }

        // 3. PowerPoint
        $pptGen = new PowerPointGenerator();
        $filename = $pptGen->generate($presentation, $slides, $images);

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'download_url' => asset('storage/presentations/' . $filename),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});
