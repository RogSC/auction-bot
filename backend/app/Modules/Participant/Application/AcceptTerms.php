<?php

declare(strict_types=1);

namespace App\Modules\Participant\Application;

use App\Models\User;
use DomainException;

final readonly class AcceptTerms
{
    public function __construct(private TermsVersion $termsVersion) {}

    public function handle(int $userId, string $version): void
    {
        if ($version !== $this->termsVersion->current()) {
            throw new DomainException('Terms version is no longer current.');
        }

        User::query()->findOrFail($userId)->update(['accepted_terms_version' => $version, 'accepted_terms_at' => now()]);
    }
}
