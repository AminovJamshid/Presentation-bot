<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "🔍 Debug: Claude API Connection\n\n";

// 1. Config tekshirish
echo "1. API Key tekshirish:\n";
$apiKey = config('telegram.anthropic_api_key');
if (empty($apiKey)) {
    echo "   ❌ API key MAVJUD EMAS!\n";
    echo "   .env fayliga ANTHROPIC_API_KEY qo'shing\n\n";
    exit(1);
} else {
    echo "   ✅ API key topildi: " . substr($apiKey, 0, 20) . "...\n\n";
}

// 2. API ga so'rov yuborish
echo "2. Claude API ga test so'rov...\n";

try {
    $response = Http::withHeaders([
        'x-api-key' => $apiKey,
        'anthropic-version' => '2023-06-01',
        'content-type' => 'application/json',
    ])
        ->timeout(30)
        ->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 100,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Salom! Faqat "Salom, men Claudeman!" deb javob ber.'
                ]
            ]
        ]);

    if ($response->successful()) {
        echo "   ✅ Claude API ishlayapti!\n\n";

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? 'Javob topilmadi';

        echo "3. Claude javobi:\n";
        echo "   {$text}\n\n";

        echo "4. Token ma'lumotlari:\n";
        echo "   Input: " . ($data['usage']['input_tokens'] ?? 0) . " tokens\n";
        echo "   Output: " . ($data['usage']['output_tokens'] ?? 0) . " tokens\n";

        echo "\n✅ Hammasi ishlayapti!\n";

    } else {
        echo "   ❌ Xatolik!\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Javob: " . $response->body() . "\n";
    }

} catch (\Exception $e) {
    echo "   ❌ Exception:\n";
    echo "   " . $e->getMessage() . "\n";
}
