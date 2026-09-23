<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Query\GetCliAuthorization;

final readonly class GetCliAuthorizationQuery
{
    public function __construct(
        public string $userCode,
    ) {
    }
}
