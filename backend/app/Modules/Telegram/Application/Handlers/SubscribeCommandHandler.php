<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

final readonly class SubscribeCommandHandler
{
    public function __construct(private StartCommandHandler $startCommandHandler) {}

    /** @param array<string, mixed> $message */
    public function handle(array $message): void
    {
        $this->startCommandHandler->handle($message);
    }
}
