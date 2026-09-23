<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

/**
 * A container-to-container call under /internal (see InternalApiClient) failed — the
 * dependency context's container is unreachable, timed out, or rejected the request. Maps
 * to 503: the caller's own logic is fine, a dependency it needs isn't answering.
 */
#[WithHttpStatus(Response::HTTP_SERVICE_UNAVAILABLE)]
#[WithLogLevel(LogLevel::ERROR)]
final class InternalApiCallFailedException extends AppException
{
    public function __construct(string $path, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Internal API call to "%s" failed', $path), previous: $previous);
    }
}
