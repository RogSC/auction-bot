<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Models\Auction;
use Carbon\CarbonImmutable;

final readonly class TelegramMessageRenderer
{
    public function welcome(): string
    {
        return 'Добро пожаловать в бот цифровых арт-аукционов.';
    }

    public function releaseWelcome(?CarbonImmutable $firstArtworkAt = null): string
    {
        $firstArtworkLine = $firstArtworkAt === null
            ? 'Дата первой работы будет объявлена позже.'
            : 'Первая работа придёт '.$firstArtworkAt->setTimezone(config('app.timezone'))->format('d.m.Y в H:i').'.';

        return "Выставка идёт 14 дней. Работы приходят сюда входящими сообщениями — в час, который назначает сама работа. Расписания на руках не будет.\n\n"
            ."Через сутки после каждой работы в ответ на неё придёт экспликация: имя художника, название, комментарий. Она приходит тихо, без уведомления, и остаётся рядом с работой в переписке.\n\n"
            ."Часть работ живёт ограниченное время и исчезает из чата. Архива нет, пересмотреть их будет негде.\n\n"
            ."Когда выставка закончится, бот пришлёт каталог — те же работы, собранные как лоты. Дальше аукцион: два дня публичных торгов с общим моментом закрытия.\n\n"
            ."Уведомления от бота — часть формата. Если их выключить, выставка продолжит идти, но пройдёт мимо.\n\n"
            .$firstArtworkLine;
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

    public function auction(Auction $auction, ?string $leaderCode, int $bidCount = 0): string
    {
        $leader = $leaderCode === null ? 'Ставок пока нет' : $leaderCode;

        return "Текущая цена: {$auction->current_price_cents} центов\nТекущий лидер: {$leader}\nСтавок: {$bidCount}";
    }

    public function refreshedAuctionCaption(string $currentCaption, Auction $auction, ?string $leaderCode, int $bidCount = 0): string
    {
        $status = $this->auction($auction, $leaderCode, $bidCount);
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
