<?php

return [
    'provider' => env('AI_PROVIDER', 'anthropic'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929'), // Yangi model
        'max_tokens' => 4096,
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4'),
        'max_tokens' => 4096,
    ],

    'prompts' => [
        'presentation' => 'Create a {slides_count}-slide presentation about: {topic}. Language: {language}. Each slide needs: title, 3-5 bullet points, speaker notes, image description. Output as JSON array only, no extra text.',

        'slide_structure' => 'JSON structure:
[
  {
    "slide_number": 1,
    "title": "Short catchy title",
    "content": ["Point 1", "Point 2", "Point 3"],
    "speaker_notes": "Brief explanation for presenter",
    "image_query": "simple image search keywords"
  }
]',
    ],
];
