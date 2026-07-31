<?php

declare(strict_types=1);

namespace App\Modules\Release\Application\Commands;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\PurchaseOffer;
use App\Models\Release;
use App\Models\ReleaseArtwork;
use App\Models\ReleaseEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ResetDemoAuctions extends Command
{
    protected $signature = 'demo:reset-auctions
                            {--force : Confirm deletion of every exhibition release and auction}
                            {--purge-audit-logs : Also delete audit records related to the removed demo data}';

    protected $description = 'Remove all exhibition releases, auctions, bids, and purchase offers for a fresh demonstration.';

    public function handle(): int
    {
        $counts = [
            'releases' => Release::query()->count(),
            'auctions' => Auction::query()->count(),
            'bids' => Bid::query()->count(),
            'purchase offers' => PurchaseOffer::query()->count(),
        ];

        $this->table(['Data', 'Records'], collect($counts)->map(fn (int $count, string $name): array => [$name, $count])->all());
        if (! $this->option('force')) {
            $this->warn('Dry run only. No data was changed. Re-run with --force to remove this demo data.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            $auctionIds = Auction::query()->pluck('id');

            // Deleting releases cascades to their events, subscriptions, deliveries, and release-artwork rows.
            Release::query()->delete();

            PurchaseOffer::query()->whereIn('auction_id', $auctionIds)->delete();
            Auction::query()->whereIn('id', $auctionIds)->update([
                'winning_bid_id' => null,
                'accepted_bid_id' => null,
            ]);
            Bid::query()->whereIn('auction_id', $auctionIds)->delete();
            Auction::query()->whereIn('id', $auctionIds)->delete();

            if ($this->option('purge-audit-logs')) {
                DB::table('audit_logs')->whereIn('auditable_type', [
                    Auction::class,
                    Bid::class,
                    PurchaseOffer::class,
                    Release::class,
                    ReleaseArtwork::class,
                    ReleaseEvent::class,
                ])->delete();
            }
        });

        $this->info('Demo auctions and exhibition releases have been removed. Artworks, users, admins, and Telegram message history were kept.');
        $this->warn('Before restarting Horizon, clear queued demo jobs so delayed jobs do not refer to deleted records.');

        return self::SUCCESS;
    }
}
