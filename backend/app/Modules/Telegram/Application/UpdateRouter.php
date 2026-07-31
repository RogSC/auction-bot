<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Modules\Telegram\Application\Handlers\CallbackQueryHandler;
use App\Modules\Telegram\Application\Handlers\StartCommandHandler;
use App\Modules\Telegram\Application\Handlers\SubscribeCommandHandler;

final readonly class UpdateRouter
{
    public function __construct(
        private StartCommandHandler $startCommandHandler,
        private SubscribeCommandHandler $subscribeCommandHandler,
        private CallbackQueryHandler $callbackQueryHandler,
    ) {}

    /** @param array<string, mixed> $update */
    public function handle(array $update): void
    {
        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->callbackQueryHandler->handle($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $command = explode(' ', (string) ($message['text'] ?? ''))[0];
        if (str_starts_with($command, '/start')) {
            $this->startCommandHandler->handle($message);

            return;
        }
        if (str_starts_with($command, '/subscribe')) {
            $this->subscribeCommandHandler->handle($message);

            return;
        }

    }
}
