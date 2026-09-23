<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class FileSizeTooLargeException extends \Exception
{
    public function __construct(int $actualSize, int $maxSize)
    {
        parent::__construct(\sprintf(
            'File size %d bytes exceeds maximum allowed size of %d bytes',
            $actualSize,
            $maxSize
        ));
    }
}
