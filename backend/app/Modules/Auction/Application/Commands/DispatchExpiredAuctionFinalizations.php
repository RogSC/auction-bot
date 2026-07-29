<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\Commands;

use App\Models\Auction;
use App\Modules\Auction\Application\Jobs\FinalizeAuctionJob;
use App\Modules\Auction\Domain\Enums\AuctionStatus;
use Illuminate\Console\Command;

final class DispatchExpiredAuctionFinalizations extends Command
{
    protected $signature = 'auctions:finalize-expired';

    protected $description = 'Queue finalization for active auctions that have ended.';

    public function handle(): int
    {
        Auction::query()
            ->where('status', AuctionStatus::Active)
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $auctionId) => FinalizeAuctionJob::dispatch($auctionId));

        return self::SUCCESS;
    }
}
