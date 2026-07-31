<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Release;
use App\Models\ReleaseArtwork;
use App\Models\User;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use App\Modules\Auction\Domain\Events\AuctionFinished;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class SendAuctionFinishedNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private TelegramBotApiClient $client) {}

    public function handle(AuctionFinished $event): void
    {
        $releaseArtwork = ReleaseArtwork::query()
            ->where('auction_id', $event->auction->id)
            ->first();
        if ($releaseArtwork !== null) {
            $this->sendReleaseFinishedNotification($releaseArtwork->release_id);

            return;
        }

        $userIds = Bid::query()->where('auction_id', $event->auction->id)->distinct()->pluck('user_id');
        $message = "Аукцион #{$event->auction->id} завершён.";

        User::query()->whereIn('id', $userIds)->whereNotNull('telegram_id')->each(
            fn (User $user) => $this->client->sendMessage($user->telegram_id, $message),
        );
    }

    private function sendReleaseFinishedNotification(int $releaseId): void
    {
        $release = Release::query()->find($releaseId);
        if ($release === null) {
            return;
        }

        $auctionIds = ReleaseArtwork::query()->where('release_id', $releaseId)->pluck('auction_id')->filter();
        if ($auctionIds->isEmpty()) {
            return;
        }

        $terminalStatuses = [
            AuctionStatus::AwaitingPayment,
            AuctionStatus::Paid,
            AuctionStatus::Completed,
            AuctionStatus::NoSale,
            AuctionStatus::Cancelled,
        ];
        $terminalStatusValues = array_map(fn (AuctionStatus $status): string => $status->value, $terminalStatuses);
        if (Auction::query()->whereIn('id', $auctionIds)->whereNotIn('status', $terminalStatusValues)->exists()) {
            return;
        }

        $soldLots = Auction::query()
            ->whereIn('id', $auctionIds)
            ->whereIn('status', [AuctionStatus::AwaitingPayment->value, AuctionStatus::Paid->value, AuctionStatus::Completed->value])
            ->count();
        $nextRelease = Release::query()
            ->where('id', '!=', $releaseId)
            ->where('status', \App\Modules\Release\Domain\Enums\ReleaseStatus::Scheduled)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();
        $nextReleaseLine = $nextRelease === null
            ? 'Следующий выпуск будет объявлен позже. Подписка сохраняется, делать ничего не нужно.'
            : 'Следующий выпуск — в '.$this->monthInPrepositionalCase($nextRelease->starts_at->month).'. Подписка сохраняется, делать ничего не нужно.';
        $message = "Торги закрыты\n\n"
            ."Ставки больше не принимаются. Продано {$soldLots} лотов из {$auctionIds->count()}. Финальные цены — в каталоге.\n\n"
            ."Победителям бот напишет отдельным сообщением в ближайшие 10 минут.\n\n"
            ."Работы, пришедшие сюда сообщениями, уходят из выпуска с ценой, владельцем и провенансом.\n\n"
            .$nextReleaseLine;

        User::query()->whereNotNull('telegram_id')->each(
            fn (User $user) => $this->client->sendMessage(
                $user->telegram_id,
                $message,
                idempotencyKey: "release-auction-closed-{$releaseId}-user-{$user->id}",
            ),
        );
    }

    private function monthInPrepositionalCase(int $month): string
    {
        return [
            1 => 'январе', 2 => 'феврале', 3 => 'марте', 4 => 'апреле',
            5 => 'мае', 6 => 'июне', 7 => 'июле', 8 => 'августе',
            9 => 'сентябре', 10 => 'октябре', 11 => 'ноябре', 12 => 'декабре',
        ][$month];
    }
}
