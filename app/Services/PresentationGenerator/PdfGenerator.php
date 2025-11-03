<?php

namespace App\Services\PresentationGenerator;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PdfGenerator
{
    /**
     * PDF yaratish (HTML dan)
     */
    public function generate($contentData, $studentInfo, $outputPath)
    {
        try {
            // HTML kontent yaratish
            $html = $this->buildHtmlContent($contentData, $studentInfo);

            // PDF yaratish
            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');

            // Papkani yaratish
            $directory = dirname($outputPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Faylni saqlash
            $pdf->save($outputPath);

            return [
                'success' => true,
                'file_path' => $outputPath,
                'file_size' => filesize($outputPath)
            ];

        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * HTML kontent yaratish
     */
    protected function buildHtmlContent($contentData, $studentInfo)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #2C3E50;
            line-height: 1.6;
        }
        .cover-page {
            text-align: center;
            padding-top: 200px;
            page-break-after: always;
        }
        .cover-page h1 {
            font-size: 32px;
            color: #2C3E50;
            margin-bottom: 50px;
        }
        .student-info {
            font-size: 18px;
            margin: 20px 0;
        }
        .content-page {
            page-break-after: always;
            padding: 20px 0;
        }
        .content-page:last-child {
            page-break-after: auto;
        }
        .content-page h2 {
            font-size: 24px;
            color: #2C3E50;
            border-bottom: 2px solid #3498DB;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .content-page ul {
            list-style-type: disc;
            padding-left: 30px;
        }
        .content-page li {
            margin: 10px 0;
            font-size: 14px;
        }
        .page-number {
            text-align: right;
            color: #95A5A6;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>
<body>';

        // Talaba ma'lumotlari (agar birinchi sahifaga)
        if ($studentInfo['info_placement'] === 'first') {
            $html .= $this->buildStudentInfoHtml($studentInfo, $contentData['title']);
        }

        // Kontent sahifalari
        foreach ($contentData['slides'] as $slideData) {
            $html .= $this->buildContentPageHtml($slideData);
        }

        // Talaba ma'lumotlari (agar oxirgi sahifaga)
        if ($studentInfo['info_placement'] === 'last') {
            $html .= $this->buildStudentInfoHtml($studentInfo, $contentData['title']);
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Talaba ma'lumotlari HTML
     */
    protected function buildStudentInfoHtml($studentInfo, $title)
    {
        $html = '<div class="cover-page">';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<div class="student-info">';
        $html .= '<p>🎓 ' . htmlspecialchars($studentInfo['university']) . '</p>';
        $html .= '<p>📚 ' . htmlspecialchars($studentInfo['direction']) . '</p>';
        $html .= '<p>👥 Guruh: ' . htmlspecialchars($studentInfo['group_name']) . '</p>';

        if (!empty($studentInfo['first_name'])) {
            $html .= '<p>👤 ' . htmlspecialchars($studentInfo['first_name']) . '</p>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Kontent sahifasi HTML
     */
    protected function buildContentPageHtml($slideData)
    {
        $html = '<div class="content-page">';
        $html .= '<h2>' . htmlspecialchars($slideData['title']) . '</h2>';
        $html .= '<ul>';

        foreach ($slideData['content'] as $point) {
            $html .= '<li>' . htmlspecialchars($point) . '</li>';
        }

        $html .= '</ul>';
        $html .= '<div class="page-number">Sahifa ' . $slideData['slide_number'] . '</div>';
        $html .= '</div>';

        return $html;
    }
}
