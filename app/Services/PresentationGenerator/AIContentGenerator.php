<?php

namespace App\Services\PresentationGenerator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIContentGenerator
{
    protected $apiKey;
    protected $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('telegram.anthropic_api_key');

        if (empty($this->apiKey)) {
            Log::warning('ANTHROPIC_API_KEY not configured, using mock data');
        }
    }

    /**
     * Prezentatsiya uchun kontent yaratish
     */
    public function generatePresentationContent($topic, $pagesCount, $university, $direction, $group)
    {
        // Agar API key yo'q bo'lsa - mock data qaytarish
        if (empty($this->apiKey)) {
            return $this->generateMockContent($topic, $pagesCount);
        }

        $prompt = $this->buildPrompt($topic, $pagesCount, $university, $direction, $group);

        try {
            // Claude API ga so'rov yuborish
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(60) // 60 soniya timeout
                ->post($this->apiUrl, [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'max_tokens' => 4096,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]);

            // Xatolikni tekshirish
            if (!$response->successful()) {
                Log::error('Claude API error: ' . $response->body());
                return $this->generateMockContent($topic, $pagesCount);
            }

            // Javobni qayta ishlash
            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';

            // Log qilish (debug uchun)
            Log::info('Claude API response received', [
                'tokens' => $data['usage'] ?? null,
                'content_length' => strlen($content)
            ]);

            // JSON ni ajratib olish
            $content = $this->extractJSON($content);
            $result = json_decode($content, true);

            // Agar JSON parse qilish muvaffaqiyatsiz bo'lsa
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON parse error: ' . json_last_error_msg());
                return $this->generateMockContent($topic, $pagesCount);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Claude API exception: ' . $e->getMessage());
            return $this->generateMockContent($topic, $pagesCount);
        }
    }

    /**
     * Prompt yaratish
     */
    protected function buildPrompt($topic, $pagesCount, $university, $direction, $group)
    {
        return "Prezentatsiya uchun professional kontent yarating.

TALABA MA'LUMOTLARI:
- Universitet: {$university}
- Yo'nalish: {$direction}
- Guruh: {$group}

PREZENTATSIYA TALABLARI:
- Mavzu: {$topic}
- Sahifalar soni: {$pagesCount}

KONTENT TALABLARI:
1. Birinchi sahifa: Mavzu bilan tanishish va umumiy ko'rinish
2. Oxirgi sahifa: Xulosa, asosiy xulosalar va tavsiyalar
3. O'rtadagi sahifalar: Mavzuni ketma-ket va mantiqiy yoritish
4. Har bir sahifada 3-5 qisqa bullet point
5. Oddiy, tushunarli va akademik til
6. O'zbek tilida yozing
7. Talaba darajasiga mos tushuntiring

MUHIM: Faqat JSON formatda javob bering, boshqa hech narsa yozmang!

JSON FORMAT:
{
  \"title\": \"Prezentatsiya umumiy sarlavhasi\",
  \"slides\": [
    {
      \"slide_number\": 1,
      \"title\": \"Sahifa sarlavhasi\",
      \"content\": [\"Birinchi nuqta\", \"Ikkinchi nuqta\", \"Uchinchi nuqta\"]
    },
    {
      \"slide_number\": 2,
      \"title\": \"Ikkinchi sahifa sarlavhasi\",
      \"content\": [\"Birinchi nuqta\", \"Ikkinchi nuqta\"]
    }
  ]
}";
    }

    /**
     * Matndan JSON ni ajratib olish
     */
    protected function extractJSON($text)
    {
        // Agar JSON kod blokida bo'lsa
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            return trim($matches[1]);
        }

        // Agar oddiy kod blokida bo'lsa
        if (preg_match('/```\s*(.*?)\s*```/s', $text, $matches)) {
            return trim($matches[1]);
        }

        // Agar { } orasida bo'lsa
        if (preg_match('/(\{.*\})/s', $text, $matches)) {
            return trim($matches[1]);
        }

        return trim($text);
    }

    /**
     * Fallback: AI ishlamasa oddiy kontent
     */
    public function generateMockContent($topic, $pagesCount)
    {
        $slides = [];

        // Birinchi sahifa
        $slides[] = [
            'slide_number' => 1,
            'title' => "Kirish: {$topic}",
            'content' => [
                "Mavzu: {$topic}",
                "Bu prezentatsiya {$pagesCount} sahifadan iborat",
                "Mavzuni batafsil o'rganamiz",
                "Amaliy va nazariy jihatlar ko'rib chiqiladi"
            ]
        ];

        // O'rtadagi sahifalar
        for ($i = 2; $i < $pagesCount; $i++) {
            $slides[] = [
                'slide_number' => $i,
                'title' => "{$topic} - Qism {$i}",
                'content' => [
                    "Mavzuning {$i}-qismi batafsil",
                    "Asosiy tushunchalar va ta'riflar",
                    "Amaliy misollar va qo'llanishlar",
                    "Muhim xulosalar va tavsiyalar"
                ]
            ];
        }

        // Oxirgi sahifa
        if ($pagesCount > 1) {
            $slides[] = [
                'slide_number' => $pagesCount,
                'title' => "Xulosa va Tavsiyalar",
                'content' => [
                    "Mavzu bo'yicha umumiy xulosalar",
                    "Asosiy o'rganilgan fikrlar",
                    "Amaliy tavsiyalar va yo'nalishlar",
                    "Qo'shimcha o'rganish uchun manbalar",
                    "Diqqat uchun rahmat!"
                ]
            ];
        }

        return [
            'title' => $topic,
            'slides' => $slides
        ];
    }
}
