<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Models\Auction;
use App\Models\User;
use App\Modules\Auction\Application\AuctionOperationException;
use App\Modules\Auction\Application\PlaceNextBid;
use App\Modules\Participant\Application\TermsVersion;
use App\Modules\Telegram\Application\TelegramMessageRenderer;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class BidCallbackHandler
{
    public function __construct(private TelegramBotApiClient $client, private TelegramMessageRenderer $renderer, private PlaceNextBid $placeNextBid, private TermsVersion $termsVersion) {}

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

        if ($user->accepted_terms_at === null || $user->accepted_terms_version !== $this->termsVersion->current()) {
            $this->client->sendMessage($chatId, $this->renderer->terms(), $this->renderer->termsKeyboard($this->termsVersion->current()));

            return true;
        }

        if ($parts[0] === 'auction_refresh') {
            $leaderCode = User::query()->find($auction->current_leader_id)?->bidder_code;
            $this->client->sendMessage($chatId, $this->renderer->auction($auction, $leaderCode), $this->renderer->auctionKeyboard($auction));

            return true;
        }

        if ($parts[0] === 'bid_prepare') {
            $this->client->sendMessage($chatId, $this->renderer->bidConfirmation($auction), $this->renderer->bidConfirmationKeyboard($auction));

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
}
