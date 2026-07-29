<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Http;

use App\Modules\Telegram\Application\UpdateRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TelegramWebhookController
{
    public function __invoke(Request $request, UpdateRouter $router): JsonResponse
    {
        $secret = config('telegram.webhook_secret');
        $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        abort_unless(is_string($secret) && $secret !== '' && is_string($providedSecret) && hash_equals($secret, $providedSecret), 403);

        $updateId = $request->integer('update_id');
        abort_unless($updateId > 0, 422, 'Telegram update_id is required.');

        $inserted = DB::table('processed_telegram_updates')->insertOrIgnore([
            'update_id' => $updateId,
            'processed_at' => now(),
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            return response()->json(['status' => 'duplicate']);
        }

        $router->handle($request->all());

        return response()->json(['status' => 'accepted']);
    }
}
