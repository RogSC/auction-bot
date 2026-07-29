<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Modules\Telegram\Application\Handlers\CallbackQueryHandler;
use App\Modules\Telegram\Application\Handlers\StartCommandHandler;

final readonly class UpdateRouter
{
    public function __construct(private StartCommandHandler $startCommandHandler, private CallbackQueryHandler $callbackQueryHandler) {}

    /** @param array<string, mixed> $update */
    public function handle(array $update): void
    {
        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->callbackQueryHandler->handle($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        if (is_array($message) && ($message['text'] ?? null) === '/start') {
            $this->startCommandHandler->handle($message);
        }
    }
}
