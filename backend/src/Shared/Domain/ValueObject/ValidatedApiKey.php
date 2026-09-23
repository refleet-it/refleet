<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class ValidatedApiKey
{
    /**
     * @param string[] $symfonyRoles
     */
    public function __construct(
        public string $apiKeyId,
        public string $accountId,
        public string $email,
        public array $symfonyRoles,
    ) {
    }
}
