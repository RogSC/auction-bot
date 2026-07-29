<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Handlers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Modules\Auction\Application\ListVisibleAuctions;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;

final readonly class MenuCallbackHandler
{
    public function __construct(private TelegramBotApiClient $client, private ListVisibleAuctions $visibleAuctions) {}

    /** @param array<string, mixed> $callbackQuery */
    public function handle(array $callbackQuery): bool
    {
        $data = $callbackQuery['data'] ?? null;
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        if (! is_string($data) || $chatId <= 0 || ! str_starts_with($data, 'menu:')) {
            return false;
        }

        $text = match ($data) {
            'menu:active' => $this->activeAuctionsText(),
            'menu:bids' => $this->myBidsText((int) ($callbackQuery['from']['id'] ?? 0)),
            'menu:completed' => $this->completedAuctionsText(),
            'menu:rules' => 'Bids use a fixed increment. The highest valid bidder wins. Payment is confirmed manually.',
            default => null,
        };
        if ($text === null) {
            return false;
        }

        $this->client->sendMessage($chatId, $text);

        return true;
    }

    private function activeAuctionsText(): string
    {
        $auctions = $this->visibleAuctions->handle();

        return $auctions->isEmpty() ? 'No active auctions right now.' : $auctions->map(fn (Auction $auction): string => "#{$auction->id}: {$auction->current_price_cents} cents")->join("\n");
    }

    private function myBidsText(int $telegramId): string
    {
        $userId = User::query()->where('telegram_id', $telegramId)->value('id');
        if ($userId === null) {
            return 'You have not placed any bids yet.';
        }

        $bids = Bid::query()->where('user_id', $userId)->latest('placed_at')->get();

        return $bids->isEmpty() ? 'You have not placed any bids yet.' : $bids->map(fn (Bid $bid): string => "Auction #{$bid->auction_id}: {$bid->amount_cents} cents ({$bid->status->value})")->join("\n");
    }

    private function completedAuctionsText(): string
    {
        $auctions = Auction::query()->whereIn('status', [AuctionStatus::Completed, AuctionStatus::NoSale])->latest('ends_at')->get();

        return $auctions->isEmpty() ? 'No completed auctions yet.' : $auctions->map(fn (Auction $auction): string => "#{$auction->id}: {$auction->current_price_cents} cents ({$auction->status->value})")->join("\n");
    }
}
