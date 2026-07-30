<?php

declare(strict_types=1);

namespace App\Modules\Release\Domain\Enums;

enum ReleaseNotificationMode: string
{
    case Loud = 'loud';
    case Silent = 'silent';
}
