<?php

namespace App\Services\Generator;

use App\Models\Presentation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Slide\Background\Image as BackgroundImage;

class PowerPointGenerator
{
    private PhpPresentation $presentation;
    private array $slideContents;
    private array $slideImages;

    /**
     * PowerPoint yaratish
     *
     * @param Presentation $presentation - DB model
     * @param array $slideContents - AI dan kelgan kontent
     * @param array $slideImages - ImageFetcher dan kelgan rasmlar
     */
    public function generate(Presentation $presentation, array $slideContents, array $slideImages = []): string
    {
        Log::info('Starting PowerPoint generation', [
            'presentation_id' => $presentation->id,
            'slides_count' => count($slideContents),
            'images_count' => count($slideImages),
        ]);

        $this->slideContents = $slideContents;
        $this->slideImages = $slideImages;
        $this->presentation = new PhpPresentation();

        // Presentation metadata
        $this->presentation->getDocumentProperties()
            ->setCreator('Presentation Bot')
            ->setTitle($presentation->topic)
            ->setSubject($presentation->topic)
            ->setDescription('Auto-generated presentation');

        // Birinchi default slaydni o'chirish
        $this->presentation->removeSlideByIndex(0);

        // Barcha slaydlarni yaratish
        foreach ($this->slideContents as $index => $slideData) {
            $this->createSlide($slideData, $index);
        }

        // Faylga saqlash
        $filename = $this->saveToFile($presentation);

        Log::info('PowerPoint generated successfully', [
            'filename' => $filename,
        ]);

        return $filename;
    }

    /**
     * Slayd yaratish
     */
    private function createSlide(array $slideData, int $index): void
    {
        $slideNumber = $slideData['slide_number'];

        Log::info('Creating slide', [
            'slide_number' => $slideNumber,
            'title' => $slideData['title'],
        ]);

        $slide = $this->presentation->createSlide();

        // Background image yoki gradient
        if (isset($this->slideImages[$slideNumber]) && $this->slideImages[$slideNumber]['success']) {
            $this->addBackgroundImage($slide, $this->slideImages[$slideNumber]);
        } else {
            $this->addGradientBackground($slide, $index);
        }

        // Title
        $this->addTitle($slide, $slideData['title']);

        // Content
        if (!empty($slideData['content'])) {
            $this->addContent($slide, $slideData['content']);
        }

        // Slide number
        $this->addSlideNumber($slide, $slideNumber);

        // Speaker notes (yangilangan)
        if (!empty($slideData['speaker_notes'])) {
            try {
                $note = $slide->getNote();
                $noteShape = $note->createRichTextShape();
                $noteShape->setWidth(960)
                    ->setHeight(720)
                    ->setOffsetX(0)
                    ->setOffsetY(0);
                $noteShape->createTextRun($slideData['speaker_notes']);
            } catch (\Exception $e) {
                Log::warning('Could not add speaker notes', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    /**
     * Background rasm qo'shish
     */
    /**
     * Background rasm qo'shish
     */
    private function addBackgroundImage($slide, array $imageData): void
    {
        try {
            $imagePath = storage_path('app/public/' . $imageData['path']);

            // Fayl mavjudligini tekshirish
            if (!file_exists($imagePath)) {
                Log::warning('Image file not found, using gradient instead', [
                    'expected_path' => $imagePath,
                    'image_data' => $imageData,
                ]);

                // Gradient ishlatamiz
                $index = $imageData['slide_number'] ?? 0;
                $this->addGradientBackground($slide, $index);
                return;
            }

            Log::info('Adding background image', [
                'path' => $imagePath,
                'size' => filesize($imagePath),
            ]);

            // Rasm o'lchamlarini tekshirish
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                Log::warning('Invalid image file, using gradient instead', [
                    'path' => $imagePath,
                ]);
                $this->addGradientBackground($slide, $imageData['slide_number'] ?? 0);
                return;
            }

            // Background rasm qo'shish
            $shape = $slide->createDrawingShape();
            $shape->setPath($imagePath)
                ->setWidth(960)
                ->setHeight(720)
                ->setOffsetX(0)
                ->setOffsetY(0);

            // Dark overlay (matn ko'rinishi uchun)
            $overlay = $slide->createRichTextShape();
            $overlay->setWidth(960)
                ->setHeight(720)
                ->setOffsetX(0)
                ->setOffsetY(0);

            $overlay->getFill()
                ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_SOLID)
                ->setStartColor(new Color('80000000')); // 50% transparent black

        } catch (\Exception $e) {
            Log::error('Error adding background image', [
                'error' => $e->getMessage(),
                'image_data' => $imageData,
            ]);

            // Fallback - gradient
            $this->addGradientBackground($slide, $imageData['slide_number'] ?? 0);
        }
    }
    /**
     * Gradient background qo'shish
     */
    private function addGradientBackground($slide, int $index): void
    {
        $gradients = config('images.fallback.gradient_colors');
        $gradient = $gradients[$index % count($gradients)];

        $slide->getFill()
            ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_GRADIENT_LINEAR)
            ->setStartColor(new Color(ltrim($gradient[0], '#')))
            ->setEndColor(new Color(ltrim($gradient[1], '#')))
            ->setRotation(45);
    }

    /**
     * Sarlavha qo'shish
     */
    private function addTitle($slide, string $title): void
    {
        $shape = $slide->createRichTextShape()
            ->setWidth(900)
            ->setHeight(100)
            ->setOffsetX(30)
            ->setOffsetY(30);

        $shape->getFill()
            ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_NONE);

        $textRun = $shape->createTextRun($title);
        $textRun->getFont()
            ->setBold(true)
            ->setSize(36)
            ->setColor(new Color('FFFFFFFF'));

        $shape->getActiveParagraph()
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    /**
     * Kontent (bullet points) qo'shish
     */
    private function addContent($slide, array $contentPoints): void
    {
        $shape = $slide->createRichTextShape()
            ->setWidth(900)
            ->setHeight(550)
            ->setOffsetX(30)
            ->setOffsetY(150);

        $shape->getFill()
            ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_NONE);

        foreach ($contentPoints as $point) {
            $textRun = $shape->createTextRun('• ' . $point);
            $textRun->getFont()
                ->setSize(24)
                ->setColor(new Color('FFFFFFFF'));

            $shape->createBreak();
            $shape->createBreak(); // Qo'shimcha bo'sh qator
        }

        $shape->getActiveParagraph()
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    /**
     * Slayd raqami qo'shish
     */
    private function addSlideNumber($slide, int $number): void
    {
        $shape = $slide->createRichTextShape()
            ->setWidth(100)
            ->setHeight(30)
            ->setOffsetX(860)
            ->setOffsetY(690);

        $textRun = $shape->createTextRun((string)$number);
        $textRun->getFont()
            ->setSize(14)
            ->setColor(new Color('CCFFFFFF'));

        $shape->getActiveParagraph()
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * Faylga saqlash
     */
    private function saveToFile(Presentation $presentation): string
    {
        $filename = 'presentation_' . $presentation->id . '_' . time() . '.pptx';
        $filepath = storage_path('app/public/presentations/' . $filename);

        // Directory yaratish
        $directory = dirname($filepath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // PowerPoint faylini saqlash
        $writer = IOFactory::createWriter($this->presentation, 'PowerPoint2007');
        $writer->save($filepath);

        // DB ga saqlash
        $presentation->update([
            'file_path' => 'presentations/' . $filename,
            'file_size' => filesize($filepath),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $filename;
    }
}
