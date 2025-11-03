<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Color;

echo "🧪 Oddiy PPTX Test\n\n";

try {
    // 1. Yangi prezentatsiya
    $presentation = new PhpPresentation();

    echo "✅ PhpPresentation yaratildi\n";

    // 2. Default slide ni o'chirish
    $presentation->removeSlideByIndex(0);
    echo "✅ Default slide o'chirildi\n";

    // 3. Yangi slide yaratish
    $slide = $presentation->createSlide();
    echo "✅ Yangi slide yaratildi\n";

    // 4. Background sozlash
    $slide->getBackground()
        ->setType(\PhpOffice\PhpPresentation\Slide\Background::TYPE_SOLID)
        ->setColor(new Color('FF4A90E2'));
    echo "✅ Background sozlandi\n";

    // 5. Matn qo'shish
    $shape = $slide->createRichTextShape()
        ->setHeight(100)
        ->setWidth(600)
        ->setOffsetX(100)
        ->setOffsetY(100);

    $textRun = $shape->createTextRun('Test Prezentatsiya');
    $textRun->getFont()->setBold(true)->setSize(44);

    echo "✅ Matn qo'shildi\n";

    // 6. Saqlash
    $outputPath = __DIR__ . '/../storage/app/presentations/simple-test.pptx';

    $writer = IOFactory::createWriter($presentation, 'PowerPoint2007');
    $writer->save($outputPath);

    echo "✅ Fayl saqlandi: $outputPath\n";

    if (file_exists($outputPath)) {
        $size = filesize($outputPath);
        echo "✅ Fayl mavjud! Hajmi: $size bytes\n";
    }

    echo "\n🎉 Test muvaffaqiyatli!\n";

} catch (\Exception $e) {
    echo "❌ Xatolik: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
