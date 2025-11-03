<?php

namespace App\Jobs;

use App\Models\Presentation;
use App\Services\PresentationGenerator\AIContentGenerator;
use App\Services\PresentationGenerator\PptxGenerator;
use App\Services\PresentationGenerator\DocxGenerator;
use App\Services\PresentationGenerator\PdfGenerator;
use App\Services\TelegramBot\BotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeneratePresentationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $presentationId;
    protected $chatId;

    /**
     * Job yaratish
     */
    public function __construct($presentationId, $chatId)
    {
        $this->presentationId = $presentationId;
        $this->chatId = $chatId;
    }

    /**
     * Job ni bajarish
     */
    public function handle()
    {
        try {
            // Prezentatsiyani olish
            $presentation = Presentation::findOrFail($this->presentationId);
            $presentation->markAsGenerating();

            // Bot service
            $botService = new BotService();

            // "Yaratilmoqda" xabari
            $botService->sendChatAction($this->chatId, 'upload_document');
            $botService->sendMessage(
                $this->chatId,
                "⏳ Prezentatsiya yaratilmoqda...\n📊 AI kontent tayyorlanmoqda..."
            );

            // 1. AI kontent yaratish
            $aiGenerator = new AIContentGenerator();
            $contentData = $aiGenerator->generatePresentationContent(
                topic: $presentation->topic,
                pagesCount: $presentation->pages_count,
                university: $presentation->university,
                direction: $presentation->direction,
                group: $presentation->group_name
            );

            if (!$contentData) {
                throw new \Exception('AI kontent yaratishda xatolik');
            }

            // Progress xabari
            $botService->sendMessage(
                $this->chatId,
                "✅ Kontent tayyor!\n📝 Fayl yaratilmoqda..."
            );

            // 2. Talaba ma'lumotlari
            $user = $presentation->user;
            $studentInfo = [
                'university' => $presentation->university,
                'direction' => $presentation->direction,
                'group_name' => $presentation->group_name,
                'info_placement' => $presentation->info_placement,
                'first_name' => $user->first_name ?? 'Talaba',
            ];

            // 3. Fayl yaratish
            $fileName = $this->generateFileName($presentation);
            $outputPath = storage_path('app/presentations/' . $fileName);

            $result = null;

            switch ($presentation->format) {
                case 'pptx':
                    $generator = new PptxGenerator();
                    $result = $generator->generate($contentData, $studentInfo, $outputPath);
                    break;

                case 'docx':
                case 'doc':
                    $generator = new DocxGenerator();
                    $result = $generator->generate($contentData, $studentInfo, $outputPath);
                    break;

                case 'pdf':
                    $generator = new PdfGenerator();
                    $result = $generator->generate($contentData, $studentInfo, $outputPath);
                    break;

                default:
                    throw new \Exception('Noma\'lum format: ' . $presentation->format);
            }

            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            // 4. Database ni yangilash
            $presentation->markAsCompleted($result['file_path'], $result['file_size']);

            // 5. Faylni yuborish
            $caption = "✅ <b>Prezentatsiya tayyor!</b>\n\n";
            $caption .= "📋 <b>Ma'lumotlar:</b>\n";
            $caption .= "📝 Mavzu: {$presentation->topic}\n";
            $caption .= "📊 Sahifalar: {$presentation->pages_count}\n";
            $caption .= "📁 Format: " . strtoupper($presentation->format) . "\n";
            $caption .= "💾 Hajmi: " . $this->formatFileSize($result['file_size']);

            $botService->sendDocument($this->chatId, $result['file_path'], $caption);

            // 6. Tugallash xabari
            $botService->sendMessage(
                $this->chatId,
                "🎉 Muvaffaqiyatli yaratildi!\n\n" .
                "Yangi prezentatsiya yaratish uchun /create ni yuboring."
            );

            Log::info("Presentation generated successfully", [
                'presentation_id' => $this->presentationId,
                'file_size' => $result['file_size']
            ]);

        } catch (\Exception $e) {
            Log::error('GeneratePresentationJob error: ' . $e->getMessage(), [
                'presentation_id' => $this->presentationId,
                'trace' => $e->getTraceAsString()
            ]);

            // Xatolik xabarini yuborish
            $botService = new BotService();
            $botService->sendMessage(
                $this->chatId,
                "❌ <b>Xatolik yuz berdi!</b>\n\n" .
                "Iltimos, qaytadan urinib ko'ring: /create\n\n" .
                "Muammo davom etsa, @admin ga murojaat qiling."
            );

            // Database ni yangilash
            if (isset($presentation)) {
                $presentation->markAsFailed($e->getMessage());
            }
        }
    }

    /**
     * Fayl nomini yaratish
     */
    protected function generateFileName($presentation)
    {
        $slug = Str::slug($presentation->topic, '-');
        $timestamp = now()->format('Y-m-d_His');
        $extension = $presentation->format;

        return "{$slug}_{$timestamp}.{$extension}";
    }

    /**
     * Fayl hajmini formatlash
     */
    protected function formatFileSize($bytes)
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
