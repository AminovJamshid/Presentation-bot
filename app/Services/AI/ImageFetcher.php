<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageFetcher
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        $this->provider = config('images.provider', 'unsplash');
        $this->config = config("images.{$this->provider}", []);

        Log::info('ImageFetcher initialized', [
            'provider' => $this->provider,
            'has_api_key' => !empty($this->config['access_key'] ?? $this->config['api_key'] ?? null),
        ]);
    }

    /**
     * Rasm qidirish va yuklab olish
     */
    public function fetchImage(string $query, int $slideNumber): array
    {
        Log::info('Fetching image', [
            'query' => $query,
            'slide_number' => $slideNumber,
            'provider' => $this->provider,
        ]);

        try {
            $imageData = match($this->provider) {
                'unsplash' => $this->fetchFromUnsplash($query),
                'pexels' => $this->fetchFromPexels($query),
                'pixabay' => $this->fetchFromPixabay($query),
                default => throw new \Exception("Unsupported image provider: {$this->provider}")
            };

            if ($imageData['success']) {
                // Rasmni yuklab olish
                $path = $this->downloadImage($imageData['url'], $slideNumber);

                Log::info('Image fetched successfully', [
                    'query' => $query,
                    'path' => $path,
                    'source' => $this->provider,
                ]);

                return [
                    'success' => true,
                    'path' => $path,
                    'url' => $imageData['url'],
                    'photographer' => $imageData['photographer'] ?? null,
                    'photographer_url' => $imageData['photographer_url'] ?? null,
                    'source' => $this->provider,
                ];
            }

            // Agar rasm topilmasa, gradient
            return $this->createGradientImage($slideNumber);

        } catch (\Exception $e) {
            Log::error('Image fetch error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            // Fallback: gradient
            return $this->createGradientImage($slideNumber);
        }
    }

    /**
     * Unsplash dan rasm qidirish
     */
    private function fetchFromUnsplash(string $query): array
    {
        if (empty($this->config['access_key'])) {
            throw new \Exception('Unsplash API key not configured');
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Client-ID ' . $this->config['access_key'],
            ])
            ->get($this->config['api_url'] . '/search/photos', [
                'query' => $query,
                'per_page' => $this->config['per_page'],
                'orientation' => $this->config['orientation'],
            ]);

        if (!$response->successful()) {
            Log::error('Unsplash API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Unsplash API error');
        }

        $data = $response->json();

        if (empty($data['results'])) {
            return ['success' => false];
        }

        $photo = $data['results'][0];

        return [
            'success' => true,
            'url' => $photo['urls']['regular'],
            'photographer' => $photo['user']['name'],
            'photographer_url' => $photo['user']['links']['html'],
        ];
    }

    /**
     * Pexels dan rasm qidirish
     */
    private function fetchFromPexels(string $query): array
    {
        if (empty($this->config['api_key'])) {
            throw new \Exception('Pexels API key not configured');
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => $this->config['api_key'],
            ])
            ->get($this->config['api_url'] . '/search', [
                'query' => $query,
                'per_page' => $this->config['per_page'],
                'orientation' => $this->config['orientation'],
            ]);

        if (!$response->successful()) {
            Log::error('Pexels API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Pexels API error');
        }

        $data = $response->json();

        if (empty($data['photos'])) {
            return ['success' => false];
        }

        $photo = $data['photos'][0];

        return [
            'success' => true,
            'url' => $photo['src']['large'],
            'photographer' => $photo['photographer'],
            'photographer_url' => $photo['photographer_url'],
        ];
    }

    /**
     * Pixabay dan rasm qidirish
     */
    private function fetchFromPixabay(string $query): array
    {
        if (empty($this->config['api_key'])) {
            throw new \Exception('Pixabay API key not configured');
        }

        $response = Http::timeout(30)
            ->get($this->config['api_url'], [
                'key' => $this->config['api_key'],
                'q' => $query,
                'image_type' => $this->config['image_type'],
                'orientation' => $this->config['orientation'],
                'min_width' => $this->config['min_width'],
                'min_height' => $this->config['min_height'],
                'per_page' => $this->config['per_page'],
                'safesearch' => 'true',
            ]);

        if (!$response->successful()) {
            Log::error('Pixabay API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Pixabay API error');
        }

        $data = $response->json();

        if (empty($data['hits'])) {
            return ['success' => false];
        }

        $photo = $data['hits'][0];

        return [
            'success' => true,
            'url' => $photo['largeImageURL'], // yoki 'webformatURL', 'fullHDURL'
            'photographer' => $photo['user'],
            'photographer_url' => 'https://pixabay.com/users/' . $photo['user'] . '-' . $photo['user_id'],
        ];
    }

    /**
     * Rasmni serverga yuklab olish
     */
    private function downloadImage(string $url, int $slideNumber): string
    {
        $response = Http::timeout(30)->get($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to download image');
        }

        $imageContent = $response->body();
        $fileSize = strlen($imageContent);

        // Max size check
        if ($fileSize > config('images.storage.max_size')) {
            throw new \Exception('Image size exceeds limit');
        }

        // Fayl nomini yaratish
        $filename = 'slide_' . $slideNumber . '_' . time() . '.jpg';
        $path = config('images.storage.path') . '/' . $filename;

        // Saqlash
        Storage::disk(config('images.storage.disk'))->put($path, $imageContent);

        return $path;
    }

    /**
     * Gradient background yaratish (fallback)
     */
    private function createGradientImage(int $slideNumber): array
    {
        Log::info('Creating gradient image', [
            'slide_number' => $slideNumber,
        ]);

        // Gradient ranglaridan birini tanlash
        $gradients = config('images.fallback.gradient_colors');
        $gradient = $gradients[$slideNumber % count($gradients)];

        // 1920x1080 gradient rasm yaratish
        $width = 1920;
        $height = 1080;
        $image = imagecreatetruecolor($width, $height);

        // Gradient ranglarni parse qilish
        $color1 = $this->hexToRgb($gradient[0]);
        $color2 = $this->hexToRgb($gradient[1]);

        // Gradient chizish
        for ($i = 0; $i < $height; $i++) {
            $ratio = $i / $height;
            $r = $color1[0] + ($color2[0] - $color1[0]) * $ratio;
            $g = $color1[1] + ($color2[1] - $color1[1]) * $ratio;
            $b = $color1[2] + ($color2[2] - $color1[2]) * $ratio;

            $color = imagecolorallocate($image, $r, $g, $b);
            imagefilledrectangle($image, 0, $i, $width, $i, $color);
        }

        // Saqlash
        $filename = 'gradient_slide_' . $slideNumber . '_' . time() . '.jpg';
        $path = config('images.storage.path') . '/' . $filename;
        $fullPath = storage_path('app/public/' . $path);

        // Directory yaratish
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        imagejpeg($image, $fullPath, 90);
        imagedestroy($image);

        return [
            'success' => true,
            'path' => $path,
            'url' => null,
            'photographer' => null,
            'photographer_url' => null,
            'source' => 'gradient',
        ];
    }

    /**
     * Hex rangni RGB ga aylantirish
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Bir nechta rasmlarni bir vaqtda olish
     */
    public function fetchMultipleImages(array $queries): array
    {
        $results = [];

        foreach ($queries as $slideNumber => $query) {
            $results[$slideNumber] = $this->fetchImage($query, $slideNumber);
        }

        return $results;
    }
}
