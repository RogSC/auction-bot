<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application;

use App\Models\Auction;
use App\Models\ReleaseArtwork;
use App\Models\User;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Support\Facades\DB;

final readonly class RefreshReleaseAuctionCards
{
    public function __construct(private TelegramBotApiClient $client, private TelegramMessageRenderer $renderer) {}

    public function handle(int $auctionId): void
    {
        $releaseArtwork = ReleaseArtwork::query()->with('release')->where('auction_id', $auctionId)->first();
        $auction = Auction::query()->find($auctionId);
        if ($releaseArtwork === null || $auction === null) {
            return;
        }

        $leaderCode = User::query()->find($auction->current_leader_id)?->bidder_code;
        $prefix = "release-auction-start-{$releaseArtwork->release_id}-user-";
        $suffix = "-artwork-{$releaseArtwork->artwork_id}";

        DB::table('telegram_messages')
            ->where('type', 'sendPhoto')
            ->whereNotNull('telegram_message_id')
            ->where('idempotency_key', 'like', $prefix.'%'.$suffix)
            ->orderBy('id')
            ->each(function (object $message) use ($auction, $leaderCode): void {
                $payload = json_decode($message->payload ?? '{}', true);
                $caption = is_array($payload) && is_string($payload['caption'] ?? null) ? $payload['caption'] : '';
                $updatedCaption = $this->renderer->refreshedAuctionCaption($caption, $auction, $leaderCode);

                try {
                    $this->client->editMessageCaption(
                        (int) $message->chat_id,
                        (int) $message->telegram_message_id,
                        $updatedCaption,
                        $this->renderer->auctionKeyboard($auction),
                    );
                } catch (\Throwable $exception) {
                    DB::table('telegram_messages')->where('id', $message->id)->update([
                        'failed_at' => now(),
                        'failure_reason' => $exception->getMessage(),
                        'updated_at' => now(),
                    ]);

                    return;
                }

                if (is_array($payload)) {
                    $payload['caption'] = $updatedCaption;
                    $payload['reply_markup'] = $this->renderer->auctionKeyboard($auction);
                    DB::table('telegram_messages')->where('id', $message->id)->update([
                        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                        'failed_at' => null,
                        'failure_reason' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
    }
}
