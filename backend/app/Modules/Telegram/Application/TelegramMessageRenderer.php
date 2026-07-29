<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Models\Auction;

final readonly class TelegramMessageRenderer
{
    public function welcome(): string
    {
        return 'Welcome to the digital art auction bot.';
    }

    /** @return array<string, array<int, array<int, array<string, string>>> > */
    public function mainMenu(): array
    {
        return [
            'inline_keyboard' => [
                [['text' => 'Active auctions', 'callback_data' => 'menu:active']],
                [['text' => 'My bids', 'callback_data' => 'menu:bids']],
                [['text' => 'Completed auctions', 'callback_data' => 'menu:completed']],
                [['text' => 'Rules', 'callback_data' => 'menu:rules']],
            ],
        ];
    }

    public function auction(Auction $auction, ?string $leaderCode): string
    {
        $leader = $leaderCode === null ? 'No bids yet' : $leaderCode;

        return "Current price: {$auction->current_price_cents} cents\nCurrent leader: {$leader}\nEnds at: {$auction->ends_at->toIso8601String()}";
    }

    /** @return array<string, array<int, array<int, array<string, string>>> > */
    public function auctionKeyboard(Auction $auction): array
    {
        return ['inline_keyboard' => [
            [['text' => 'Place next bid', 'callback_data' => "bid_prepare:{$auction->id}:{$auction->version}"]],
            [['text' => 'Refresh', 'callback_data' => "auction_refresh:{$auction->id}:{$auction->version}"]],
        ]];
    }

    public function bidConfirmation(Auction $auction): string
    {
        $amount = $auction->current_price_cents + $auction->bid_increment_cents;

        return "Confirm your bid of {$amount} cents.";
    }

    public function terms(): string
    {
        return 'Before placing your first bid, please accept the Terms of Service.';
    }

    /** @return array<string, array<int, array<int, array<string, string>>> > */
    public function termsKeyboard(string $version): array
    {
        return ['inline_keyboard' => [[['text' => 'I accept', 'callback_data' => "terms_accept:{$version}"]]]];
    }

    /** @return array<string, array<int, array<int, array<string, string>>> > */
    public function bidConfirmationKeyboard(Auction $auction): array
    {
        return ['inline_keyboard' => [
            [['text' => 'Confirm bid', 'callback_data' => "bid_confirm:{$auction->id}:{$auction->version}"]],
        ]];
    }
}
