<?php

declare(strict_types=1);

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'payment_contact_name' => env('PAYMENT_CONTACT_NAME', 'представитель проекта'),
    'payment_contact_handle' => env('PAYMENT_CONTACT_HANDLE', '@project'),
];
