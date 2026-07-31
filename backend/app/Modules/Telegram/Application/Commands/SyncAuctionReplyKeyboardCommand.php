<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Commands;

use App\Modules\Telegram\Application\Jobs\SyncAuctionReplyKeyboardJob;
use Illuminate\Console\Command;

final class SyncAuctionReplyKeyboardCommand extends Command
{
    protected $signature = 'telegram:sync-auction-keyboard';

    protected $description = 'Send the current active-auction reply keyboard to every Telegram user.';

    public function handle(): int
    {
        SyncAuctionReplyKeyboardJob::dispatch('manual-'.now()->format('YmdHis'));
        $this->info('Auction reply keyboard synchronization has been queued.');

        return self::SUCCESS;
    }
}
