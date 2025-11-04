<?php

return [
    'provider' => env('IMAGE_PROVIDER', 'unsplash'), // unsplash, pexels, pixabay

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
        'api_url' => 'https://api.unsplash.com',
        'per_page' => 1,
        'orientation' => 'landscape',
    ],

    'pexels' => [
        'api_key' => env('PEXELS_API_KEY'),
        'api_url' => 'https://api.pexels.com/v1',
        'per_page' => 1,
        'orientation' => 'landscape',
    ],

    'pixabay' => [
        'api_key' => env('PIXABAY_API_KEY'),
        'api_url' => 'https://pixabay.com/api',
        'per_page' => 3,
        'image_type' => 'photo', // photo, illustration, vector
        'orientation' => 'horizontal', // horizontal, vertical
        'min_width' => 1920,
        'min_height' => 1080,
    ],

    'fallback' => [
        'use_gradient' => true,
        'gradient_colors' => [
            ['#667eea', '#764ba2'], // Purple
            ['#f093fb', '#f5576c'], // Pink
            ['#4facfe', '#00f2fe'], // Blue
            ['#43e97b', '#38f9d7'], // Green
            ['#fa709a', '#fee140'], // Orange
            ['#30cfd0', '#330867'], // Teal
            ['#a8edea', '#fed6e3'], // Pastel
            ['#ff9a9e', '#fecfef'], // Rose
        ],
    ],

    'storage' => [
        'disk' => 'public',
        'path' => 'presentation-images',
        'max_size' => 5 * 1024 * 1024, // 5MB
    ],
];
