<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class DomainException extends AppException
{
    public function __construct(string $message, int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
