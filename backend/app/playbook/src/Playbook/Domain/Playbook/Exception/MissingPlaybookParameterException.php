<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Exception;

use App\Shared\Domain\Exception\ValidationException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
#[WithLogLevel(LogLevel::INFO)]
final class MissingPlaybookParameterException extends ValidationException
{
    public function __construct(string $playbookName, string $parameterName)
    {
        parent::__construct(\sprintf('Playbook "%s" requires the "%s" parameter.', $playbookName, $parameterName));
    }
}
