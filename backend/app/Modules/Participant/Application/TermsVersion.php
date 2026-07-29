<?php

declare(strict_types=1);

namespace App\Modules\Participant\Application;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class TermsVersion
{
    public function current(): string
    {
        $value = DB::table('settings')->where('key', 'terms.current_version')->value('value');
        $version = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : null;
        if (! is_string($version) || $version === '') {
            throw new RuntimeException('Current terms version is not configured.');
        }

        return $version;
    }
}
