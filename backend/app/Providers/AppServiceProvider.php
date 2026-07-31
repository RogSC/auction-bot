<?php

namespace App\Providers;

use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Auction\Domain\Events\AuctionFinished;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Auction\Domain\Events\PaymentConfirmed;
use App\Modules\Auction\Domain\Events\PaymentRequested;
use App\Modules\Auction\Domain\Events\PurchaseOffered;
use App\Modules\Notification\Application\Listeners\SendAuctionExtendedNotification;
use App\Modules\Notification\Application\Listeners\SendAuctionFinishedNotification;
use App\Modules\Notification\Application\Listeners\SendBidPlacedNotification;
use App\Modules\Notification\Application\Listeners\SendLifecycleNotification;
use App\Modules\Notification\Application\Listeners\SendPaymentConfirmedNotification;
use App\Modules\Telegram\Application\Listeners\SyncAuctionReplyKeyboardListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(BidPlaced::class, SendBidPlacedNotification::class);
        Event::listen(AuctionExtended::class, SendAuctionExtendedNotification::class);
        Event::listen(AuctionFinished::class, SendAuctionFinishedNotification::class);
        Event::listen(PaymentConfirmed::class, SendPaymentConfirmedNotification::class);
        Event::listen(AuctionStarted::class, SendLifecycleNotification::class);
        Event::listen(AuctionStarted::class, SyncAuctionReplyKeyboardListener::class);
        Event::listen(AuctionFinished::class, SyncAuctionReplyKeyboardListener::class);
        Event::listen(AuctionCancelled::class, SyncAuctionReplyKeyboardListener::class);
        Event::listen(PaymentRequested::class, SendLifecycleNotification::class);
        Event::listen(AuctionCancelled::class, SendLifecycleNotification::class);
        Event::listen(PurchaseOffered::class, SendLifecycleNotification::class);
    }
}
