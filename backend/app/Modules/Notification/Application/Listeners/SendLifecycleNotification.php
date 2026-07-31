<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\Bid;
use App\Models\ReleaseArtwork;
use App\Models\User;
use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\PaymentRequested;
use App\Modules\Auction\Domain\Events\PurchaseOffered;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendLifecycleNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(AuctionStarted|PaymentRequested|AuctionCancelled|PurchaseOffered $event): void
    {
        match (true) {
            $event instanceof AuctionStarted => null,
            $event instanceof PaymentRequested => $this->sendPaymentRequested($event),
            $event instanceof AuctionCancelled => null,
            $event instanceof PurchaseOffered => $this->sendToUser($event->offer->offered_to_user_id, "Вам предложена покупка этой работы за {$event->offer->amount_cents} центов."),
        };
    }

    private function sendToUser(?int $userId, string $message): void
    {
        $user = $userId === null ? null : User::query()->find($userId);
        if ($user?->telegram_id !== null) {
            $this->client->sendMessage($user->telegram_id, $message);
        }
    }

    private function sendPaymentRequested(PaymentRequested $event): void
    {
        $auction = $event->auction;
        $releaseArtwork = ReleaseArtwork::query()->with('artwork')->where('auction_id', $auction->id)->first();
        $winningBid = $auction->winning_bid_id === null ? null : Bid::query()->find($auction->winning_bid_id);
        $lotNumber = $releaseArtwork?->position ?? $auction->id;
        $title = $releaseArtwork?->artwork?->title ?? 'Работа';
        $artist = $releaseArtwork?->artwork?->artist_name ?? 'Автор не указан';
        $amount = $winningBid?->amount_cents ?? $auction->current_price_cents;
        $contactName = (string) config('telegram.payment_contact_name');
        $contactHandle = (string) config('telegram.payment_contact_handle');

        $message = "Лот №{$lotNumber}. {$title}, {$artist}\n"
            ."Ваша ставка {$this->formatUsd($amount)} стала последней.\n\n"
            ."В течение двух часов с вами свяжется {$contactName} от лица проекта: подтвердит покупку, пришлёт счёт и договор. Оплата проходит банковским переводом по счёту, вне бота.\n\n"
            ."После оплаты вы получаете:\n"
            ."— лот в форме, указанной в его описании\n"
            ."— сертификат с номером, датой и подписью художника\n"
            ."— договор о передаче прав\n"
            ."— документацию доставки: когда работа пришла подписчикам и сколько прожила в чате\n\n"
            ."Срок оплаты — 3 дня с момента выставления счёта.\n"
            ."Вопросы до этого момента: {$contactHandle}";

        $this->sendToUser($auction->auction_winner_id, $message);
    }

    private function formatUsd(int $cents): string
    {
        return '$'.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
