<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Commands;

use App\Models\User;
use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Console\Command;

final class ClearTelegramDemoMenus extends Command
{
    protected $signature = 'telegram:clear-demo-menus';

    protected $description = 'Remove bot commands and reply keyboards for the presentation mode.';

    public function handle(TelegramBotApiClient $client): int
    {
        $client->deleteMyCommands();

        User::query()->whereNotNull('telegram_id')->orderBy('id')->each(function (User $user) use ($client): void {
            $client->sendMessage(
                $user->telegram_id,
                'Режим презентации включён.',
                ['remove_keyboard' => true],
                "clear-demo-menu-user-{$user->id}",
                true,
            );
        });

        $this->info('Telegram command menu and reply keyboards have been cleared.');

        return self::SUCCESS;
    }
}
