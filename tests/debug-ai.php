<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "🔍 Debug: Claude API Connection\n\n";

// 1. API Key tekshirish
echo "1. API Key tekshirish:\n";
$apiKey = config('services.anthropic.api_key');

if (empty($apiKey)) {
    die("   ❌ API key topilmadi! .env faylini tekshiring.\n");
}

echo "   ✅ API key topildi: " . substr($apiKey, 0, 20) . "...\n\n";

// 2. Claude API ga test so'rov
echo "2. Claude API ga test so'rov...\n";

try {
    $response = Http::withHeaders([
        'x-api-key' => $apiKey,
        'anthropic-version' => '2023-06-01',
        'content-type' => 'application/json',
    ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
        'model' => 'claude-sonnet-4-20250514',  // ← YANGILANDI
        'max_tokens' => 100,
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Salom! Qisqa javob ber: sen kimssan?'
            ]
        ]
    ]);

    if ($response->successful()) {
        $data = $response->json();
        echo "   ✅ Muvaffaqiyatli!\n";
        echo "   Model: " . ($data['model'] ?? 'unknown') . "\n";

        if (isset($data['content'][0]['text'])) {
            echo "   Response: " . substr($data['content'][0]['text'], 0, 100) . "...\n";
        }

        echo "\n✅ Claude AI ishlayapti! Bot haqiqiy kontent yaratadi!\n";

    } else {
        $error = $response->json();
        echo "   ❌ Xatolik!\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Javob: " . json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

} catch (\Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}
