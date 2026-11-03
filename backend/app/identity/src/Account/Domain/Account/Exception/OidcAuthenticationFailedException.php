<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Exception;

use App\Shared\Domain\Exception\DetailedAppException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

/**
 * The issuer refused the code, could not be reached, or returned a token that does not
 * verify.
 *
 * `$reason` stays out of the response body on purpose. Told apart, "no such code",
 * "signature does not verify" and "wrong audience" let a caller probe the flow, and none of
 * them is actionable for the person signing in — so the body carries one generic message and
 * the reason goes to the log, where an operator can read it.
 */
#[WithHttpStatus(Response::HTTP_UNAUTHORIZED)]
#[WithLogLevel(LogLevel::WARNING)]
final class OidcAuthenticationFailedException extends DetailedAppException
{
    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct(
            message: 'Single sign-on failed.',
            errorCode: 'OIDC_AUTHENTICATION_FAILED',
            details: [
                'suggestion' => 'Start signing in again, and contact your administrator if it keeps failing',
            ]
        );
    }
}
