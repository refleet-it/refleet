<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class FileAccessDeniedException extends \Exception
{
    public function __construct(string $fileId)
    {
        parent::__construct(\sprintf('Access denied to file with ID "%s"', $fileId));
    }
}
