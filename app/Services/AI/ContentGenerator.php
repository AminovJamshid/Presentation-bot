<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Presentation;

class ContentGenerator
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'anthropic');
        $this->config = config("ai.{$this->provider}", []);

        // Agar config bo'sh bo'lsa, default qiymatlar
        if (empty($this->config)) {
            $this->config = [
                'api_key' => null,
                'model' => 'claude-sonnet-4-5-20250929',
                'max_tokens' => 4096,
            ];
        }

        Log::info('ContentGenerator initialized', [
            'provider' => $this->provider,
            'model' => $this->config['model'] ?? 'unknown',
            'has_api_key' => !empty($this->config['api_key']),
        ]);
    }

    /**
     * Prezentatsiya uchun kontent generatsiya qilish
     */
    public function generatePresentationContent(Presentation $presentation): array
    {
        Log::info('Starting content generation', [
            'presentation_id' => $presentation->id,
            'topic' => $presentation->topic,
            'pages_count' => $presentation->pages_count,
        ]);

        try {
            $prompt = $this->buildPrompt($presentation);

            Log::info('Prompt built', [
                'prompt_length' => strlen($prompt),
            ]);

            $content = match($this->provider) {
                'anthropic' => $this->generateWithAnthropic($prompt),
                'openai' => $this->generateWithOpenAI($prompt),
                default => throw new \Exception("Unsupported AI provider: {$this->provider}")
            };

            $slides = $this->parseContent($content);

            Log::info('Content generated successfully', [
                'slides_count' => count($slides),
            ]);

            return $slides;

        } catch (\Exception $e) {
            Log::error('AI Content Generation Error', [
                'presentation_id' => $presentation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Log::warning('Using fallback content');

            // Fallback: oddiy kontent
            return $this->generateFallbackContent($presentation);
        }
    }

    /**
     * Prompt yaratish
     */
    private function buildPrompt(Presentation $presentation): string
    {
        $mainPrompt = str_replace(
            ['{topic}', '{slides_count}', '{language}'],
            [
                $presentation->topic,
                $presentation->pages_count,
                'uzbek'
            ],
            config('ai.prompts.presentation')
        );

        $structurePrompt = config('ai.prompts.slide_structure');

        return $mainPrompt . "\n\n" . $structurePrompt;
    }

    /**
     * Anthropic Claude orqali generatsiya
     */
    private function generateWithAnthropic(string $prompt): string
    {
        Log::info('Calling Anthropic API', [
            'model' => $this->config['model'],
            'prompt_length' => strlen($prompt),
            'max_tokens' => $this->config['max_tokens'],
        ]);

        $startTime = microtime(true);

        try {
            $response = Http::timeout(120) // 2 daqiqa timeout
            ->withHeaders([
                'x-api-key' => $this->config['api_key'],
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->config['model'],
                'max_tokens' => $this->config['max_tokens'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            $duration = microtime(true) - $startTime;

            Log::info('Anthropic API Response', [
                'status' => $response->status(),
                'duration' => round($duration, 2) . 's',
                'success' => $response->successful(),
            ]);

            if (!$response->successful()) {
                Log::error('Anthropic API Error Response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Anthropic API Error: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Anthropic response parsed', [
                'has_content' => isset($data['content'][0]['text']),
                'content_length' => isset($data['content'][0]['text']) ? strlen($data['content'][0]['text']) : 0,
            ]);

            return $data['content'][0]['text'] ?? '';

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Anthropic API Connection Error', [
                'error' => $e->getMessage(),
                'duration' => round(microtime(true) - $startTime, 2) . 's',
            ]);
            throw $e;
        }
    }

    /**
     * OpenAI orqali generatsiya
     */
    private function generateWithOpenAI(string $prompt): string
    {
        Log::info('Calling OpenAI API', [
            'model' => $this->config['model'],
            'prompt_length' => strlen($prompt),
        ]);

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->config['model'],
                'max_tokens' => $this->config['max_tokens'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional presentation content creator.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API Error: ' . $response->body());
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * AI javobini parse qilish
     */
    private function parseContent(string $content): array
    {
        Log::info('Parsing AI content', [
            'content_length' => strlen($content),
        ]);

        // JSON ni topish
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonStr = $matches[1];
            Log::info('Found JSON in code block');
        } elseif (preg_match('/\[.*\]/s', $content, $matches)) {
            $jsonStr = $matches[0];
            Log::info('Found JSON array');
        } else {
            $jsonStr = $content;
            Log::info('Using raw content as JSON');
        }

        $slides = json_decode($jsonStr, true);

        if (!is_array($slides)) {
            Log::error('JSON parsing failed', [
                'json_error' => json_last_error_msg(),
                'content_preview' => substr($content, 0, 200),
            ]);
            throw new \Exception('Invalid JSON response from AI');
        }

        Log::info('Content parsed successfully', [
            'slides_count' => count($slides),
        ]);

        return $slides;
    }

    /**
     * Fallback kontent (AI ishlamasa)
     */
    private function generateFallbackContent(Presentation $presentation): array
    {
        Log::info('Generating fallback content', [
            'presentation_id' => $presentation->id,
            'pages_count' => $presentation->pages_count,
        ]);

        $slides = [];

        // Title slide
        $slides[] = [
            'slide_number' => 1,
            'title' => $presentation->topic,
            'content' => [
                'Professional Presentation',
                'Created with AI assistance'
            ],
            'speaker_notes' => 'Welcome to this presentation about ' . $presentation->topic,
            'image_query' => 'professional presentation background'
        ];

        // Content slides
        for ($i = 2; $i < $presentation->pages_count; $i++) {
            $slides[] = [
                'slide_number' => $i,
                'title' => "Key Point #" . ($i - 1),
                'content' => [
                    'Important information about ' . $presentation->topic,
                    'Detailed explanation will be provided',
                    'Supporting evidence and examples'
                ],
                'speaker_notes' => 'This slide covers important aspects of the topic.',
                'image_query' => $presentation->topic . ' illustration'
            ];
        }

        // Conclusion slide
        $slides[] = [
            'slide_number' => $presentation->pages_count,
            'title' => 'Thank You',
            'content' => [
                'Questions?',
                'Contact information',
                'Additional resources'
            ],
            'speaker_notes' => 'Thank you for your attention. Open for questions.',
            'image_query' => 'thank you professional'
        ];

        return $slides;
    }
}
