<?php

namespace App\Services\TelegramBot;

use App\Models\User;
use App\Models\ConversationState as ConversationStateModel;
use App\Models\Presentation;
use App\Enums\ConversationState;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    protected $botService;

    public function __construct()
    {
        $this->botService = app(BotService::class);
    }

    /**
     * Suhbatni boshlash (/create komandasi)
     */
    public function startConversation($chatId, $user)
    {
        // Eski suhbatni tozalash
        $state = ConversationStateModel::where('user_telegram_id', $user->telegram_id)->first();

        if ($state) {
            $state->clear();
        }

        // Yangi suhbat boshlash
        ConversationStateModel::startConversation(
            $user->telegram_id,
            ConversationState::AWAITING_UNIVERSITY
        );

        // Birinchi savolni berish
        $message = "🎓 <b>Universitet/Institut nomini yozing:</b>\n\n";
        $message .= "Masalan: TATU, TDTU, TIIAME...";

        $this->botService->sendMessage($chatId, $message);
    }

    /**
     * Oddiy xabarni qayta ishlash
     */
    public function handleMessage($chatId, $text, $user)
    {
        // Foydalanuvchining hozirgi holatini topish
        $stateModel = ConversationStateModel::where('user_telegram_id', $user->telegram_id)->first();

        // Agar holat yo'q bo'lsa
        if (!$stateModel || $stateModel->current_state == ConversationState::IDLE) {
            $this->botService->sendMessage(
                $chatId,
                "Prezentatsiya yaratish uchun /create ni yuboring!"
            );
            return;
        }

        // Muddati tugaganmi?
        if ($stateModel->isExpired()) {
            $stateModel->clear();
            $this->botService->sendMessage(
                $chatId,
                "⏰ Vaqt tugadi. Qaytadan boshlang: /create"
            );
            return;
        }

        // Muddatni uzaytirish
        $stateModel->extendExpiry();

        // Holatga qarab javob berish
        switch ($stateModel->current_state) {
            case ConversationState::AWAITING_UNIVERSITY:
                $this->handleUniversity($chatId, $text, $stateModel);
                break;

            case ConversationState::AWAITING_DIRECTION:
                $this->handleDirection($chatId, $text, $stateModel);
                break;

            case ConversationState::AWAITING_GROUP:
                $this->handleGroup($chatId, $text, $stateModel);
                break;

            case ConversationState::AWAITING_TOPIC:
                $this->handleTopic($chatId, $text, $stateModel);
                break;

            case ConversationState::AWAITING_PAGES:
                $this->handlePages($chatId, $text, $stateModel);
                break;

            default:
                $this->botService->sendMessage($chatId, "Noma'lum holat. /create bilan qaytadan boshlang.");
        }
    }

    /**
     * Callback query (tugma bosilganda)
     */
    public function handleCallback($chatId, $data, $user)
    {
        $stateModel = ConversationStateModel::where('user_telegram_id', $user->telegram_id)->first();

        if (!$stateModel) {
            return;
        }

        // Callback data ga qarab harakat qilish
        if (str_starts_with($data, 'placement_')) {
            $this->handlePlacementCallback($chatId, $data, $stateModel);
        } elseif (str_starts_with($data, 'format_')) {
            $this->handleFormatCallback($chatId, $data, $stateModel);
        }
    }

    /**
     * Universitet nomini qayta ishlash
     */
    protected function handleUniversity($chatId, $text, $stateModel)
    {
        // Saqlash
        $stateModel->setData('university', $text);
        $stateModel->setState(ConversationState::AWAITING_DIRECTION);

        // Keyingi savol
        $message = "📚 <b>Yo'nalishingizni yozing:</b>\n\n";
        $message .= "Masalan: Dasturlash, Iqtisod, Muhandislik...";

        $this->botService->sendMessage($chatId, $message);
    }

    /**
     * Yo'nalishni qayta ishlash
     */
    protected function handleDirection($chatId, $text, $stateModel)
    {
        $stateModel->setData('direction', $text);
        $stateModel->setState(ConversationState::AWAITING_GROUP);

        $message = "👥 <b>Guruhingizni yozing:</b>\n\n";
        $message .= "Masalan: AI-21, EK-19, IT-23...";

        $this->botService->sendMessage($chatId, $message);
    }

    /**
     * Guruhni qayta ishlash
     */
    protected function handleGroup($chatId, $text, $stateModel)
    {
        $stateModel->setData('group_name', $text);
        $stateModel->setState(ConversationState::AWAITING_PLACEMENT);

        // Inline keyboard bilan savol
        $message = "📄 <b>Talaba ma'lumotlari qayerga qo'shilsin?</b>";

        $keyboard = [
            [
                ['text' => '📄 Birinchi sahifa', 'callback_data' => 'placement_first'],
                ['text' => '📑 Oxirgi sahifa', 'callback_data' => 'placement_last']
            ]
        ];

        $this->botService->sendMessageWithInlineKeyboard($chatId, $message, $keyboard);
    }

    /**
     * Placement callback
     */
    protected function handlePlacementCallback($chatId, $data, $stateModel)
    {
        $placement = str_replace('placement_', '', $data);
        $stateModel->setData('info_placement', $placement);
        $stateModel->setState(ConversationState::AWAITING_TOPIC);

        $message = "📝 <b>Prezentatsiya mavzusini yozing:</b>\n\n";
        $message .= "Masalan: Python dasturlash tili, Sun'iy intellekt asoslari...";

        $this->botService->sendMessage($chatId, $message);
    }

    /**
     * Mavzuni qayta ishlash
     */
    protected function handleTopic($chatId, $text, $stateModel)
    {
        $stateModel->setData('topic', $text);
        $stateModel->setState(ConversationState::AWAITING_PAGES);

        $minPages = config('telegram.min_pages', 3);
        $maxPages = config('telegram.max_pages', 50);

        $message = "📊 <b>Nechta sahifa bo'lsin?</b>\n\n";
        $message .= "Raqam yozing ({$minPages}-{$maxPages} orasida)";

        $this->botService->sendMessage($chatId, $message);
    }

    /**
     * Sahifalar sonini qayta ishlash
     */
    protected function handlePages($chatId, $text, $stateModel)
    {
        $pages = (int)$text;
        $minPages = config('telegram.min_pages', 3);
        $maxPages = config('telegram.max_pages', 50);

        // Validatsiya
        if ($pages < $minPages || $pages > $maxPages) {
            $this->botService->sendMessage(
                $chatId,
                "❌ Noto'g'ri qiymat! {$minPages} dan {$maxPages} gacha raqam kiriting."
            );
            return;
        }

        $stateModel->setData('pages_count', $pages);
        $stateModel->setState(ConversationState::AWAITING_FORMAT);

        // Format tanlash
        $message = "📁 <b>Qaysi formatda fayl yaratilsin?</b>";

        $keyboard = [
            [
                ['text' => '📊 PowerPoint (PPTX)', 'callback_data' => 'format_pptx'],
            ],
            [
                ['text' => '📄 Word (DOCX)', 'callback_data' => 'format_docx'],
            ],
            [
                ['text' => '📕 PDF', 'callback_data' => 'format_pdf'],
            ]
        ];

        $this->botService->sendMessageWithInlineKeyboard($chatId, $message, $keyboard);
    }

    /**
     * Format callback
     */
    protected function handleFormatCallback($chatId, $data, $stateModel)
    {
        $format = str_replace('format_', '', $data);
        $stateModel->setData('format', $format);

        // Ma'lumotlarni olish
        $allData = $stateModel->getData();

        // Prezentatsiya yaratish
        $this->createPresentation($chatId, $stateModel->user_telegram_id, $allData, $stateModel);
    }

    /**
     * Prezentatsiya yaratish (hozircha placeholder)
     */
    protected function createPresentation($chatId, $userId, $data, $stateModel)
    {
        try {
            // Database ga saqlash
            $presentation = Presentation::create([
                'user_telegram_id' => $userId,
                'university' => $data['university'],
                'direction' => $data['direction'],
                'group_name' => $data['group_name'],
                'info_placement' => $data['info_placement'],
                'topic' => $data['topic'],
                'pages_count' => $data['pages_count'],
                'format' => $data['format'],
                'status' => 'pending',
            ]);

            // Suhbatni tozalash
            $stateModel->clear();

            // Xabar yuborish
            $message = "✅ <b>Ma'lumotlar qabul qilindi!</b>\n\n";
            $message .= "📋 Qisqacha:\n";
            $message .= "🎓 Universitet: {$data['university']}\n";
            $message .= "📚 Yo'nalish: {$data['direction']}\n";
            $message .= "👥 Guruh: {$data['group_name']}\n";
            $message .= "📝 Mavzu: {$data['topic']}\n";
            $message .= "📊 Sahifalar: {$data['pages_count']}\n";
            $message .= "📁 Format: " . strtoupper($data['format']) . "\n\n";
            $message .= "⏳ Prezentatsiya tayyorlanmoqda...\n";
            $message .= "⏱️ Bu biroz vaqt olishi mumkin.";

            $this->botService->sendMessage($chatId, $message);

            // TODO: Keyingi bosqichda - haqiqiy prezentatsiya yaratish
            // Hozircha faqat ma'lumotlarni saqladik

        } catch (\Exception $e) {
            Log::error('Create presentation error: ' . $e->getMessage());
            $this->botService->sendMessage($chatId, "❌ Xatolik yuz berdi. Qaytadan urinib ko'ring: /create");
        }
    }

    /**
     * Suhbatni bekor qilish
     */
    public function cancelConversation($chatId, $user)
    {
        $stateModel = ConversationStateModel::where('user_telegram_id', $user->telegram_id)->first();

        if ($stateModel) {
            $stateModel->clear();
        }

        $this->botService->sendMessage($chatId, "❌ Jarayon bekor qilindi.\n\nYangi prezentatsiya yaratish uchun /create ni yuboring.");
    }
}
