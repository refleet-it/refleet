<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class NotFoundException extends AppException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message);
    }
}
