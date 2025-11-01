<?php

echo "🔍 ENV File Check\n\n";

// 1. .env fayl bormi?
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    echo "❌ .env fayl topilmadi!\n";
    exit(1);
}

echo "✅ .env fayl mavjud\n\n";

// 2. .env ni o'qish
$envContent = file_get_contents($envPath);

// 3. ANTHROPIC_API_KEY ni qidirish
if (preg_match('/ANTHROPIC_API_KEY\s*=\s*["\']?(.*?)["\']?\s*$/m', $envContent, $matches)) {
    echo "✅ ANTHROPIC_API_KEY topildi .env da\n";
    $key = trim($matches[1], '"\'');
    echo "   Key: " . substr($key, 0, 20) . "...\n\n";
} else {
    echo "❌ ANTHROPIC_API_KEY topilmadi .env da\n\n";
    echo "Quyidagi qatorni .env ga qo'shing:\n";
    echo 'ANTHROPIC_API_KEY="sk-ant-api03-your-key-here"' . "\n\n";
}

// 4. Laravel config tekshirish
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$configKey = config('telegram.anthropic_api_key');

if (empty($configKey)) {
    echo "❌ Laravel config da API key YO'Q\n";
    echo "   Cache tozalang: php artisan config:clear\n";
} else {
    echo "✅ Laravel config da API key mavjud\n";
    echo "   Key: " . substr($configKey, 0, 20) . "...\n";
}
