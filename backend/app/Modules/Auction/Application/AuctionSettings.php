<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application;

use Illuminate\Support\Facades\DB;

final class AuctionSettings
{
    public function integer(string $key): int
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        if ($value === null || ! is_numeric(json_decode($value, true, flags: JSON_THROW_ON_ERROR))) {
            throw new AuctionOperationException("Missing or invalid setting [$key].");
        }

        return (int) json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }
}
