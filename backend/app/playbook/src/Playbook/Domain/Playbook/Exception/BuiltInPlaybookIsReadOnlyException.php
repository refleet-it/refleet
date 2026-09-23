<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Exception;

use App\Shared\Domain\Exception\DomainException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_CONFLICT)]
#[WithLogLevel(LogLevel::INFO)]
final class BuiltInPlaybookIsReadOnlyException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Built-in playbooks ship with Refleet and cannot be edited or deleted; duplicate one into the organization instead.');
    }
}
