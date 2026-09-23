<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class ValidationException extends AppException
{
    public function __construct(string $message = 'Validation failed')
    {
        parent::__construct($message);
    }
}
