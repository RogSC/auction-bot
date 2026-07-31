<?php

declare(strict_types=1);

namespace App\Modules\Release\Application;

use App\Models\Auction;
use App\Models\Artwork;
use App\Models\ReleaseDelivery;
use App\Models\ReleaseEvent;
use App\Models\ReleaseSubscription;
use App\Modules\Auction\Application\ActivateAuction;
use App\Modules\Release\Domain\Enums\ReleaseDeliveryStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventStatus;
use App\Modules\Release\Domain\Enums\ReleaseEventType;
use App\Modules\Release\Domain\Enums\ReleaseNotificationMode;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class DeliverReleaseEvent
{
    public function __construct(private TelegramBotApiClient $telegram, private ActivateAuction $activateAuction) {}

    public function handle(int $eventId): void
    {
        $event = $this->claim($eventId);
        if ($event === null) {
            return;
        }

        try {
            $event->loadMissing(['release.releaseArtworks.artwork', 'release.releaseArtworks.auction', 'artwork', 'auction']);
            if ($event->release->status !== ReleaseStatus::Running) {
                $event->update(['status' => ReleaseEventStatus::Cancelled]);

                return;
            }

            match ($event->type) {
                ReleaseEventType::DeliverArtwork => $this->deliverArtwork($event),
                ReleaseEventType::DeliverExplanation => $this->deliverExplanation($event),
                ReleaseEventType::DeleteArtworkMessage => $this->deleteArtworkMessages($event),
                ReleaseEventType::SendCatalog => $this->sendCatalog($event),
                ReleaseEventType::ActivateAuction => $this->activateAuction($event),
            };

            $event->update([
                'status' => ReleaseEventStatus::Completed,
                'processed_at' => now(),
                'failure_reason' => null,
            ]);
        } catch (\Throwable $exception) {
            ReleaseEvent::query()->whereKey($eventId)->update([
                'status' => ReleaseEventStatus::Pending,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function claim(int $eventId): ?ReleaseEvent
    {
        return DB::transaction(function () use ($eventId): ?ReleaseEvent {
            $event = ReleaseEvent::query()->lockForUpdate()->findOrFail($eventId);
            $isStaleProcessing = $event->status === ReleaseEventStatus::Processing
                && $event->updated_at->lessThan(now()->subMinutes(5));

            if ($event->scheduled_at->isFuture()
                || $event->status === ReleaseEventStatus::Completed
                || $event->status === ReleaseEventStatus::Cancelled
                || ($event->status === ReleaseEventStatus::Processing && ! $isStaleProcessing)) {
                return null;
            }

            $event->update([
                'status' => ReleaseEventStatus::Processing,
                'attempts' => $event->attempts + 1,
            ]);

            return $event->fresh();
        });
    }

    /** @return Collection<int, ReleaseSubscription> */
    private function recipients(ReleaseEvent $event): Collection
    {
        return ReleaseSubscription::query()
            ->with('user')
            ->where('release_id', $event->release_id)
            ->where('subscribed_at', '<=', $event->scheduled_at)
            ->where(function ($query) use ($event): void {
                $query->whereNull('unsubscribed_at')->orWhere('unsubscribed_at', '>', $event->scheduled_at);
            })
            ->whereHas('user', fn ($query) => $query->whereNotNull('telegram_id'))
            ->orderBy('id')
            ->get();
    }

    private function deliverArtwork(ReleaseEvent $event): void
    {
        if ($event->artwork === null) {
            throw new RuntimeException('Artwork delivery event must reference an artwork.');
        }

        $caption = $this->payloadText($event, 'caption');
        foreach ($this->recipients($event) as $subscription) {
            $delivery = $this->delivery($event, $subscription->user_id);
            if ($delivery->status === ReleaseDeliveryStatus::Sent) {
                continue;
            }

            $messageId = $this->telegram->sendPhoto(
                $subscription->user->telegram_id,
                $event->artwork->preview_disk,
                $event->artwork->preview_path,
                $caption,
                "release-event-{$event->id}-user-{$subscription->user_id}",
            );
            if ($messageId === null) {
                throw new RuntimeException('Telegram did not return a message ID for the artwork delivery.');
            }

            $delivery->update([
                'telegram_message_id' => $messageId,
                'status' => ReleaseDeliveryStatus::Sent,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);
        }
    }

    private function deliverExplanation(ReleaseEvent $event): void
    {
        $text = $this->payloadText($event, 'text');
        if ($text === null) {
            throw new RuntimeException('Explanation event must contain payload.text.');
        }

        foreach ($this->recipients($event) as $subscription) {
            $delivery = $this->delivery($event, $subscription->user_id);
            if ($delivery->status === ReleaseDeliveryStatus::Sent) {
                continue;
            }

            $replyToMessageId = $this->artworkMessageIdFor($event, $subscription->user_id);
            $messageId = $this->telegram->sendMessage(
                $subscription->user->telegram_id,
                $text,
                idempotencyKey: "release-event-{$event->id}-user-{$subscription->user_id}",
                disableNotification: $event->notification_mode === ReleaseNotificationMode::Silent,
                replyToMessageId: $replyToMessageId,
            );
            if ($messageId === null) {
                throw new RuntimeException('Telegram did not return a message ID for the explanation delivery.');
            }

            $delivery->update([
                'telegram_message_id' => $messageId,
                'status' => ReleaseDeliveryStatus::Sent,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);
        }
    }

    private function deleteArtworkMessages(ReleaseEvent $event): void
    {
        if ($event->artwork_id === null) {
            throw new RuntimeException('Artwork deletion event must reference an artwork.');
        }

        foreach ($this->recipients($event) as $subscription) {
            $delivery = $this->delivery($event, $subscription->user_id);
            if ($delivery->status === ReleaseDeliveryStatus::Deleted) {
                continue;
            }

            $messageId = $this->artworkMessageIdFor($event, $subscription->user_id);
            if ($messageId !== null) {
                $this->telegram->deleteMessage(
                    $subscription->user->telegram_id,
                    $messageId,
                    "release-delete-{$event->id}-user-{$subscription->user_id}",
                );
                $this->markArtworkMessageDeleted($event, $subscription->user_id, $messageId);
            }

            $delivery->update([
                'status' => ReleaseDeliveryStatus::Deleted,
                'deleted_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);
        }
    }

    private function sendCatalog(ReleaseEvent $event): void
    {
        $catalogItems = $this->catalogItems($event);
        foreach ($this->recipients($event) as $subscription) {
            $delivery = $this->delivery($event, $subscription->user_id);
            if ($delivery->status === ReleaseDeliveryStatus::Sent) {
                continue;
            }

            $firstMessageId = $this->telegram->sendMessage(
                $subscription->user->telegram_id,
                'Каталог работ:',
                idempotencyKey: "release-catalog-header-{$event->id}-user-{$subscription->user_id}",
            );
            if ($firstMessageId === null) {
                throw new RuntimeException('Telegram did not return a message ID for the catalog delivery.');
            }

            $startMessage = $this->catalogStartMessage($catalogItems);
            if ($startMessage !== null) {
                $messageId = $this->telegram->sendMessage(
                    $subscription->user->telegram_id,
                    $startMessage,
                    idempotencyKey: "release-catalog-start-{$event->id}-user-{$subscription->user_id}",
                );
            }

            $delivery->update([
                'telegram_message_id' => $firstMessageId,
                'status' => ReleaseDeliveryStatus::Sent,
                'sent_at' => now(),
            ]);
        }
    }

    private function activateAuction(ReleaseEvent $event): void
    {
        if ($event->auction_id === null) {
            throw new RuntimeException('Auction activation event must reference an auction.');
        }

        $auction = Auction::query()->findOrFail($event->auction_id);
        if ($auction->status === \App\Modules\Auction\Domain\Enums\AuctionStatus::Scheduled) {
            $this->activateAuction->handle($auction->id);
        }
    }

    private function delivery(ReleaseEvent $event, int $userId): ReleaseDelivery
    {
        return ReleaseDelivery::query()->firstOrCreate([
            'release_event_id' => $event->id,
            'user_id' => $userId,
        ]);
    }

    private function artworkMessageIdFor(ReleaseEvent $event, int $userId): ?int
    {
        if ($event->artwork_id === null) {
            return null;
        }

        $messageId = ReleaseDelivery::query()
            ->where('user_id', $userId)
            ->where('status', ReleaseDeliveryStatus::Sent)
            ->whereNotNull('telegram_message_id')
            ->whereHas('releaseEvent', function ($query) use ($event): void {
                $query->where('release_id', $event->release_id)
                    ->where('artwork_id', $event->artwork_id)
                    ->where('type', ReleaseEventType::DeliverArtwork);
            })
            ->orderByDesc('id')
            ->value('telegram_message_id');

        return is_int($messageId) ? $messageId : null;
    }

    private function markArtworkMessageDeleted(ReleaseEvent $event, int $userId, int $messageId): void
    {
        ReleaseDelivery::query()
            ->where('user_id', $userId)
            ->where('telegram_message_id', $messageId)
            ->whereHas('releaseEvent', function ($query) use ($event): void {
                $query->where('release_id', $event->release_id)
                    ->where('artwork_id', $event->artwork_id)
                    ->where('type', ReleaseEventType::DeliverArtwork);
            })
            ->update(['status' => ReleaseDeliveryStatus::Deleted, 'deleted_at' => now()]);
    }

    private function payloadText(ReleaseEvent $event, string $key): ?string
    {
        $value = $event->payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return Collection<int, array{lotNumber: int, artwork: Artwork, auction: Auction}> */
    private function catalogItems(ReleaseEvent $event): Collection
    {
        return $event->release->releaseArtworks->values()->map(function ($releaseArtwork, int $index): ?array {
            $auction = $releaseArtwork->auction;
            $artwork = $releaseArtwork->artwork;
            if ($auction === null || $artwork === null) {
                return null;
            }

            return ['lotNumber' => $index + 1, 'artwork' => $artwork, 'auction' => $auction];
        })->filter()->values();
    }

    /** @param Collection<int, array{lotNumber: int, artwork: Artwork, auction: Auction}> $catalogItems */
    private function catalogStartMessage(Collection $catalogItems): ?string
    {
        if ($catalogItems->isEmpty()) {
            return null;
        }

        $startsAt = $catalogItems
            ->map(fn (array $item) => $item['auction']->starts_at)
            ->unique(fn ($startsAt): string => $startsAt->toIso8601String())
            ->values();

        if ($startsAt->count() === 1) {
            return 'Аукцион начнётся '.$this->formatAuctionStart($startsAt->first()).'.';
        }

        $lines = ['Аукционы начнутся:'];
        foreach ($catalogItems as $item) {
            $lines[] = sprintf('Лот №%d — %s', $item['lotNumber'], $this->formatAuctionStart($item['auction']->starts_at));
        }

        return implode("\n", $lines);
    }

    private function formatAuctionStart(\DateTimeInterface $startsAt): string
    {
        return \Carbon\CarbonImmutable::instance($startsAt)
            ->setTimezone(config('app.timezone'))
            ->format('d.m.Y H:i');
    }
}
