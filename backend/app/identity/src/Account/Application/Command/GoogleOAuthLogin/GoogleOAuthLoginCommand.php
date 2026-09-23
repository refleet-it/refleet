<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\GoogleOAuthLogin;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class GoogleOAuthLoginCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $googleId,
        public bool $marketingConsent = false,
    ) {
    }
}
