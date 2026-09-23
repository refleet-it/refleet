<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Employee\Exception;

use App\Shared\Domain\Exception\NotFoundException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

#[WithHttpStatus(Response::HTTP_NOT_FOUND)]
#[WithLogLevel(LogLevel::INFO)]
final class EmployeeNotInOrganizationException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('No such employee in this organization');
    }
}
