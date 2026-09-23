<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class FileStorageException extends \Exception
{
    public function __construct(string $message = 'File storage error occurred', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
