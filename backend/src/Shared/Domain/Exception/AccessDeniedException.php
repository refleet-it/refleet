<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class AccessDeniedException extends AppException
{
    public function __construct(string $message = 'Access denied')
    {
        parent::__construct($message);
    }
}
