<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\OidcLogin;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Both values come out of a verified id_token, never off the request: the controller hands
 * over what the issuer signed, not what the browser sent.
 */
final readonly class OidcLoginCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $subject,
        public bool $marketingConsent = false,
    ) {
    }
}
