<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Models\Auction;

final readonly class TelegramMessageRenderer
{
    public function welcome(): string
    {
        return 'Добро пожаловать в бот цифровых арт-аукционов.';
    }

    public function releaseWelcome(): string
    {
        return 'Вы подписаны. Новые работы будут приходить сюда автоматически.';
    }

    /** @return array<string, array<int, array<int, array<string, string>>> > */
    public function mainMenu(): array
    {
        return [
            'inline_keyboard' => [
                [['text' => 'Активные аукционы', 'callback_data' => 'menu:active']],
                [['text' => 'Мои ставки', 'callback_data' => 'menu:bids']],
                [['text' => 'Завершённые аукционы', 'callback_data' => 'menu:completed']],
                [['text' => 'Правила', 'callback_data' => 'menu:rules']],
            ],
        ];
    }

    public function auction(Auction $auction, ?string $leaderCode): string
    {
        $leader = $leaderCode === null ? 'Ставок пока нет' : $leaderCode;

        return "Текущая цена: {$auction->current_price_cents} центов\nТекущий лидер: {$leader}\nОкончание: {$auction->ends_at->format('d.m.Y H:i')}";
    }

    public function refreshedAuctionCaption(string $currentCaption, Auction $auction, ?string $leaderCode): string
    {
        $status = $this->auction($auction, $leaderCode);
        $lotCaption = preg_replace('/(?:\R|^)Текущая цена:.*\z/us', '', $currentCaption);
        $lotCaption = rtrim($lotCaption ?? $currentCaption);

        if ($lotCaption === '') {
            return $status;
        }

        return "{$lotCaption}\n\n{$status}";
    }

    /** @return array<string, array<int, array<int, array<string, string>>> > */
    public function auctionKeyboard(Auction $auction): array
    {
        return ['inline_keyboard' => [
            [['text' => 'Сделать ставку', 'callback_data' => "bid_confirm:{$auction->id}:{$auction->version}"]],
            [['text' => 'Обновить', 'callback_data' => "auction_refresh:{$auction->id}:{$auction->version}"]],
        ]];
    }

    public function terms(): string
    {
        return 'Перед первой ставкой необходимо принять правила.';
    }

    /** @return array<string, array<int, array<int, array<string, string>>> > */
    public function termsKeyboard(string $version): array
    {
        return ['inline_keyboard' => [[['text' => 'Принимаю правила', 'callback_data' => "terms_accept:{$version}"]]]];
    }

}
