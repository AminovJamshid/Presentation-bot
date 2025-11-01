<?php

namespace App\Services\TelegramBot;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotService
{
    protected $token;
    protected $apiUrl;

    public function __construct()
    {
        $this->token = config('telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Update ni qayta ishlash
     */
    public function handleUpdate($update)
    {
        try {
            // Message bormi?
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }

            // Callback query bormi?
            elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }

        } catch (\Exception $e) {
            Log::error('BotService handleUpdate error: ' . $e->getMessage());
        }
    }

    /**
     * Oddiy xabarni qayta ishlash
     */
    protected function handleMessage($message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'];

        // Foydalanuvchini saqlash
        $user = $this->getOrCreateUser($from);

        // Komanda yoki oddiy matnmi?
        if (str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $text, $user);
        } else {
            // Oddiy matn - ConversationService ga yuborish
            $conversationService = new ConversationService();
            $conversationService->handleMessage($chatId, $text, $user);
        }
    }

    /**
     * Komandalarni qayta ishlash
     */
    protected function handleCommand($chatId, $command, $user)
    {
        // Komandani tozalash (parametrlarni olib tashlash)
        $command = explode(' ', $command)[0];

        switch ($command) {
            case '/start':
                $this->handleStartCommand($chatId, $user);
                break;

            case '/create':
                $this->handleCreateCommand($chatId, $user);
                break;

            case '/cancel':
                $this->handleCancelCommand($chatId, $user);
                break;

            case '/help':
                $this->handleHelpCommand($chatId);
                break;

            default:
                $this->sendMessage($chatId, "Noma'lum komanda. /help ni yuboring.");
        }
    }

    /**
     * /start komandasi
     */
    protected function handleStartCommand($chatId, $user)
    {
        $firstName = $user->first_name ?? 'Foydalanuvchi';

        $message = "👋 Assalomu alaykum, {$firstName}!\n\n";
        $message .= "🎓 Men prezentatsiya yaratuvchi botman.\n\n";
        $message .= "📋 <b>Nima qila olaman:</b>\n";
        $message .= "• PowerPoint (PPTX) yaratish\n";
        $message .= "• Word (DOCX) yaratish\n";
        $message .= "• PDF yaratish\n\n";
        $message .= "🚀 Boshlash uchun /create ni yuboring!\n";
        $message .= "❓ Yordam kerakmi? /help ni yuboring.";

        $this->sendMessage($chatId, $message);
    }

    /**
     * /create komandasi - yangi prezentatsiya yaratish
     */
    protected function handleCreateCommand($chatId, $user)
    {
        $conversationService = new ConversationService();
        $conversationService->startConversation($chatId, $user);
    }

    /**
     * /cancel komandasi - jarayonni bekor qilish
     */
    protected function handleCancelCommand($chatId, $user)
    {
        $conversationService = new ConversationService();
        $conversationService->cancelConversation($chatId, $user);
    }

    /**
     * /help komandasi
     */
    protected function handleHelpCommand($chatId)
    {
        $message = "📖 <b>Yordam:</b>\n\n";
        $message .= "/start - Botni boshlash\n";
        $message .= "/create - Yangi prezentatsiya yaratish\n";
        $message .= "/cancel - Jarayonni bekor qilish\n";
        $message .= "/help - Yordam\n\n";
        $message .= "🤝 Muammo bo'lsa, @admin ga murojaat qiling.";

        $this->sendMessage($chatId, $message);
    }

    /**
     * Callback query (inline button bosilganda)
     */
    protected function handleCallbackQuery($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];
        $from = $callbackQuery['from'];

        $user = $this->getOrCreateUser($from);

        // ConversationService ga topshirish
        $conversationService = new ConversationService();
        $conversationService->handleCallback($chatId, $data, $user);

        // Callback queryni answer qilish (yuklash belgisi yo'qoladi)
        $this->answerCallbackQuery($callbackQuery['id']);
    }

    /**
     * Foydalanuvchini olish yoki yaratish
     */
    protected function getOrCreateUser($from)
    {
        return User::updateOrCreate(
            ['telegram_id' => $from['id']],
            [
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'last_active_at' => now(),
            ]
        );
    }

    /**
     * Oddiy xabar yuborish
     */
    public function sendMessage($chatId, $text, $replyMarkup = null)
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return Http::post("{$this->apiUrl}/sendMessage", $data);
    }

    /**
     * Inline keyboard bilan xabar yuborish
     */
    public function sendMessageWithInlineKeyboard($chatId, $text, $buttons)
    {
        $replyMarkup = [
            'inline_keyboard' => $buttons
        ];

        return $this->sendMessage($chatId, $text, $replyMarkup);
    }

    /**
     * Callback query ga javob
     */
    public function answerCallbackQuery($callbackQueryId, $text = null)
    {
        $data = ['callback_query_id' => $callbackQueryId];

        if ($text) {
            $data['text'] = $text;
        }

        return Http::post("{$this->apiUrl}/answerCallbackQuery", $data);
    }

    /**
     * Fayl yuborish
     */
    public function sendDocument($chatId, $filePath, $caption = '')
    {
        $data = [
            'chat_id' => $chatId,
            'caption' => $caption,
        ];

        // Fayl borligini tekshirish
        if (!file_exists($filePath)) {
            Log::error("File not found: {$filePath}");
            return false;
        }

        return Http::attach(
            'document',
            file_get_contents($filePath),
            basename($filePath)
        )->post("{$this->apiUrl}/sendDocument", $data);
    }

    /**
     * Rasm yuborish
     */
    public function sendPhoto($chatId, $photoPath, $caption = '')
    {
        $data = [
            'chat_id' => $chatId,
            'caption' => $caption,
        ];

        if (!file_exists($photoPath)) {
            Log::error("Photo not found: {$photoPath}");
            return false;
        }

        return Http::attach(
            'photo',
            file_get_contents($photoPath),
            basename($photoPath)
        )->post("{$this->apiUrl}/sendPhoto", $data);
    }

    /**
     * Typing action (... yozmoqda)
     */
    public function sendChatAction($chatId, $action = 'typing')
    {
        $data = [
            'chat_id' => $chatId,
            'action' => $action,  // typing, upload_document, upload_photo
        ];

        return Http::post("{$this->apiUrl}/sendChatAction", $data);
    }

    /**
     * Xabarni tahrirlash
     */
    public function editMessageText($chatId, $messageId, $text, $replyMarkup = null)
    {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return Http::post("{$this->apiUrl}/editMessageText", $data);
    }

    /**
     * Xabarni o'chirish
     */
    public function deleteMessage($chatId, $messageId)
    {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        return Http::post("{$this->apiUrl}/deleteMessage", $data);
    }
}
