<?php

require __DIR__ . '/../vendor/autoload.php';

// Laravel app ni yuklash
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\PresentationGenerator\AIContentGenerator;

echo "🤖 Claude AI Test...\n\n";

$generator = new AIContentGenerator();

// Kontent yaratish
echo "⏳ Kontent yaratilmoqda...\n";

$content = $generator->generatePresentationContent(
    topic: 'Python dasturlash asoslari',
    pagesCount: 5,
    university: 'TATU',
    direction: 'Dasturlash',
    group: 'AI-21'
);

if ($content) {
    echo "✅ Muvaffaqiyatli!\n\n";
    echo "📊 Natija:\n";
    echo "Sarlavha: " . $content['title'] . "\n";
    echo "Sahifalar soni: " . count($content['slides']) . "\n\n";

    // Birinchi sahifani ko'rsatish
    echo "🔍 Birinchi sahifa:\n";
    echo "  Sarlavha: " . $content['slides'][0]['title'] . "\n";
    echo "  Kontent:\n";
    foreach ($content['slides'][0]['content'] as $point) {
        echo "    • {$point}\n";
    }

} else {
    echo "❌ Xatolik yuz berdi!\n";
}

echo "\n✅ Test tugadi!\n";
