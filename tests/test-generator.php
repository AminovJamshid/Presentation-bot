<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\PresentationGenerator\PptxGenerator;
use App\Services\PresentationGenerator\AIContentGenerator;

echo "🧪 Generator Test\n\n";

// 1. AI kontent
echo "1. AI kontent yaratish...\n";
$aiGen = new AIContentGenerator();
$content = $aiGen->generatePresentationContent(
    'Test mavzu',
    3,
    'TATU',
    'IT',
    'AI-21'
);

if (!$content) {
    die("❌ AI kontent yaratilmadi!\n");
}

echo "✅ AI kontent tayyor\n\n";

// 2. PPTX yaratish
echo "2. PowerPoint yaratish...\n";

$studentInfo = [
    'university' => 'TATU',
    'direction' => 'IT',
    'group_name' => 'AI-21',
    'info_placement' => 'first',
    'first_name' => 'Test User'
];

$outputPath = storage_path('app/presentations/test.pptx');

try {
    $pptxGen = new PptxGenerator();
    $result = $pptxGen->generate($content, $studentInfo, $outputPath);

    if ($result['success']) {
        echo "✅ PowerPoint yaratildi!\n";
        echo "📁 Fayl: {$result['file_path']}\n";
        echo "💾 Hajmi: {$result['file_size']} bytes\n";

        if (file_exists($outputPath)) {
            echo "✅ Fayl mavjud!\n";
        } else {
            echo "❌ Fayl topilmadi!\n";
        }
    } else {
        echo "❌ Xatolik: {$result['error']}\n";
    }

} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
