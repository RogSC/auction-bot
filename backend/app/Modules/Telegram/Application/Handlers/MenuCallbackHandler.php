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
            'menu:rules' => 'Ставка всегда повышается на фиксированный шаг. Побеждает участник с наивысшей действительной ставкой. Оплата подтверждается администратором вручную.',
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

        return $auctions->isEmpty() ? 'Сейчас нет активных аукционов.' : $auctions->map(fn (Auction $auction): string => "Аукцион #{$auction->id}: {$auction->current_price_cents} центов")->join("\n");
    }

    private function myBidsText(int $telegramId): string
    {
        $userId = User::query()->where('telegram_id', $telegramId)->value('id');
        if ($userId === null) {
            return 'У вас пока нет ставок.';
        }

        $bids = Bid::query()->where('user_id', $userId)->latest('placed_at')->get();

        return $bids->isEmpty() ? 'У вас пока нет ставок.' : $bids->map(fn (Bid $bid): string => "Аукцион #{$bid->auction_id}: {$bid->amount_cents} центов ({$this->bidStatusLabel($bid->status->value)})")->join("\n");
    }

    private function completedAuctionsText(): string
    {
        $auctions = Auction::query()->whereIn('status', [AuctionStatus::Completed, AuctionStatus::NoSale])->latest('ends_at')->get();

        return $auctions->isEmpty() ? 'Завершённых аукционов пока нет.' : $auctions->map(fn (Auction $auction): string => "Аукцион #{$auction->id}: {$auction->current_price_cents} центов ({$this->auctionStatusLabel($auction->status->value)})")->join("\n");
    }

    private function bidStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'активна',
            'outbid' => 'перебита',
            'cancelled' => 'отменена',
            'winning' => 'победившая',
            'disqualified' => 'дисквалифицирована',
            default => $status,
        };
    }

    private function auctionStatusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'завершён',
            'no_sale' => 'не продан',
            'cancelled' => 'отменён',
            default => $status,
        };
    }
}
