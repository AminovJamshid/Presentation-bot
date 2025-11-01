<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramBot\BotService;

class TelegramWebhookController extends Controller
{
    protected $botService;

    public function __construct(BotService $botService)
    {
        $this->botService = $botService;
    }

    /**
     * Telegram webhook handler
     */
    public function handle(Request $request)
    {
        try {
            $update = $request->all();

            Log::info('Telegram Update:', $update);

            $this->botService->handleUpdate($update);

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook o'rnatish
     * Browser: http://yoursite.com/api/telegram/set-webhook
     */
    public function setWebhook()
    {
        try {
            $token = config('telegram.bot_token');
            $webhookUrl = config('telegram.webhook_url');

            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'TELEGRAM_BOT_TOKEN sozlanmagan!'
                ], 400);
            }

            // Telegram API ga so'rov yuborish
            $url = "https://api.telegram.org/bot{$token}/setWebhook";

            $response = file_get_contents($url . '?' . http_build_query([
                    'url' => $webhookUrl
                ]));

            $result = json_decode($response, true);

            if ($result['ok']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook muvaffaqiyatli o\'rnatildi!',
                    'webhook_url' => $webhookUrl,
                    'result' => $result
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Webhook o\'rnatishda xatolik',
                'result' => $result
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook ma'lumotlarini olish
     */
    public function getWebhookInfo()
    {
        try {
            $token = config('telegram.bot_token');
            $url = "https://api.telegram.org/bot{$token}/getWebhookInfo";

            $response = file_get_contents($url);
            $result = json_decode($response, true);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
