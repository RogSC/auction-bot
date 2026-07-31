<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Models\Auction;
use App\Models\User;
use App\Modules\Auction\Application\AuctionOperationException;
use App\Modules\Auction\Application\PlaceNextBid;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class BidCallbackHandler
{
    public function __construct(private TelegramBotApiClient $client, private TelegramMessageRenderer $renderer, private PlaceNextBid $placeNextBid) {}

    /** @param array<string, mixed> $callbackQuery */
    public function handle(array $callbackQuery): bool
    {
        $data = $callbackQuery['data'] ?? null;
        $telegramId = (int) ($callbackQuery['from']['id'] ?? 0);
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        if (! is_string($data) || $telegramId <= 0 || $chatId <= 0) {
            return false;
        }

        $parts = explode(':', $data);
        if (count($parts) !== 3 || ! in_array($parts[0], ['auction_refresh', 'bid_prepare', 'bid_confirm'], true)) {
            return false;
        }

        $auction = Auction::query()->find((int) $parts[1]);
        $user = User::query()->where('telegram_id', $telegramId)->first();
        if ($auction === null || $user === null) {
            return true;
        }

        if ($parts[0] === 'auction_refresh') {
            $leaderCode = User::query()->find($auction->current_leader_id)?->bidder_code;
            $this->refreshAuctionMessage($callbackQuery, $chatId, $auction, $leaderCode);

            return true;
        }

        try {
            $this->placeNextBid->handle($auction->id, $user->id, (int) $parts[2]);
        } catch (AuctionOperationException $exception) {
            $this->client->sendMessage($chatId, $exception->getMessage());

            return true;
        }

        $auction->refresh();
        $leaderCode = User::query()->find($auction->current_leader_id)?->bidder_code;
        $this->client->sendMessage($chatId, $this->renderer->auction($auction, $leaderCode), $this->renderer->auctionKeyboard($auction));

        return true;
    }

    /** @param array<string, mixed> $callbackQuery */
    private function refreshAuctionMessage(array $callbackQuery, int $chatId, Auction $auction, ?string $leaderCode): void
    {
        $message = $callbackQuery['message'] ?? null;
        $messageId = is_array($message) ? ($message['message_id'] ?? null) : null;
        $keyboard = $this->renderer->auctionKeyboard($auction);

        if (! is_int($messageId)) {
            $this->client->sendMessage($chatId, $this->renderer->auction($auction, $leaderCode), $keyboard);

            return;
        }

        try {
            if (isset($message['photo'])) {
                $caption = is_string($message['caption'] ?? null) ? $message['caption'] : '';
                $this->client->editMessageCaption(
                    $chatId,
                    $messageId,
                    $this->renderer->refreshedAuctionCaption($caption, $auction, $leaderCode),
                    $keyboard,
                );
            } else {
                $this->client->editMessageText($chatId, $messageId, $this->renderer->auction($auction, $leaderCode), $keyboard);
            }
        } catch (\Throwable) {
            $this->client->sendMessage($chatId, $this->renderer->auction($auction, $leaderCode), $keyboard);
        }
    }
}
