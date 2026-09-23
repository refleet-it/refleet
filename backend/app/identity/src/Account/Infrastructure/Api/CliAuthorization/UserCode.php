<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\CliAuthorization;

final class UserCode
{
    /** Hex from CliAuthorizationCodeGenerator; the bound also keeps `/claim` from matching as a code. */
    public const string REQUIREMENT = '[0-9a-f]{24}';
}
