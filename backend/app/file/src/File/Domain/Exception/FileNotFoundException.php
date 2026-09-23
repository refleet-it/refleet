<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

final class FileNotFoundException extends NotFoundException
{
    public function __construct(string $fileId)
    {
        parent::__construct(\sprintf('File with ID "%s" not found', $fileId));
    }
}
