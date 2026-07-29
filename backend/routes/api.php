<?php

declare(strict_types=1);

use App\Modules\Telegram\Infrastructure\Http\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class);
