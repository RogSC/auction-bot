<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Models\Auction;
use App\Models\User;
use App\Modules\Auction\Application\ListVisibleAuctions;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use App\Modules\Telegram\Application\ShowCurrentReleaseLots;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class AuctionReplyKeyboardHandler
{
    public function __construct(
        private TelegramBotApiClient $client,
        private ListVisibleAuctions $visibleAuctions,
        private TelegramMessageRenderer $renderer,
        private ShowCurrentReleaseLots $showCurrentReleaseLots,
    ) {}

    /** @param array<string, mixed> $message */
    public function handle(array $message): bool
    {
        $text = $message['text'] ?? null;
        $chatId = (int) ($message['chat']['id'] ?? 0);
        if (! is_string($text) || $chatId <= 0) {
            return false;
        }
        if ($text === 'Все лоты') {
            $this->showCurrentReleaseLots->handle($chatId);

            return true;
        }
        if (preg_match('/^Аукцион #(\d+)$/u', $text, $matches) !== 1) {
            return false;
        }

        $auction = $this->visibleAuctions->handle()->firstWhere('id', (int) $matches[1]);
        if (! $auction instanceof Auction) {
            $this->client->sendMessage($chatId, 'Этот аукцион уже недоступен.');

            return true;
        }

        $leaderCode = User::query()->find($auction->current_leader_id)?->bidder_code;
        $this->client->sendMessage($chatId, $this->renderer->auction($auction, $leaderCode), $this->renderer->auctionKeyboard($auction));

        return true;
    }
}
