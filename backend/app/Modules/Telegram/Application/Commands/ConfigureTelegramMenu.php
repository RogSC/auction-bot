<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Application\Commands;

use App\Modules\Telegram\Infrastructure\TelegramBotApiClient;
use Illuminate\Console\Command;

final class ConfigureTelegramMenu extends Command
{
    protected $signature = 'telegram:configure-menu';

    protected $description = 'Publish the Telegram bot command menu.';

    public function handle(TelegramBotApiClient $client): int
    {
        $client->setMyCommands([
            ['command' => 'start', 'description' => 'Открыть меню'],
            ['command' => 'subscribe', 'description' => 'Подписаться на текущий выпуск'],
        ]);

        $this->info('Telegram command menu has been published.');

        return self::SUCCESS;
    }
}
