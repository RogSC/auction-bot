<?php

declare(strict_types=1);

namespace App\Modules\Release\Domain\Enums;

enum ReleaseDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Deleted = 'deleted';
    case Failed = 'failed';
}
