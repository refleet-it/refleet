<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Shared\Infrastructure\Listener;

use App\Shared\Domain\Exception\DetailedAppException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(Response::HTTP_UNPROCESSABLE_ENTITY)]
final class FakeDetailedException extends DetailedAppException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Something went wrong',
            errorCode: 'FAKE_DETAILED_ERROR',
            details: ['context' => 'test'],
            field: 'fieldName'
        );
    }
}
