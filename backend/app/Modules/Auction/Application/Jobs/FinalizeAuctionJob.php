<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\Jobs;

use App\Modules\Auction\Application\FinalizeAuction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class FinalizeAuctionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $auctionId) {}

    public function handle(FinalizeAuction $finalizeAuction): void
    {
        $finalizeAuction->handle($this->auctionId);
    }
}
