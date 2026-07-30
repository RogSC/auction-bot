<?php

declare(strict_types=1);

namespace App\Modules\Release\Domain\Enums;

enum ReleaseEventType: string
{
    case DeliverArtwork = 'deliver_artwork';
    case DeliverExplanation = 'deliver_explanation';
    case DeleteArtworkMessage = 'delete_artwork_message';
    case SendCatalog = 'send_catalog';
    case ActivateAuction = 'activate_auction';
}
