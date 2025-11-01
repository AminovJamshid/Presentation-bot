<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'presentation_bot'),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', ''),

    'commands_path' => app_path('Services/TelegramBot/Commands'),
    'download_path' => storage_path('app/telegram'),
    'upload_path' => storage_path('app/presentations'),

    'admins' => [],
    'conversation_timeout' => 15,
    'max_pages' => 50,
    'min_pages' => 3,

    // AI Configuration
    'ai_provider' => env('AI_PROVIDER', 'claude'),
    'anthropic_api_key' => env('ANTHROPIC_API_KEY', ''),  // ← BU QATOR!
    'openai_api_key' => env('OPENAI_API_KEY', ''),
];
