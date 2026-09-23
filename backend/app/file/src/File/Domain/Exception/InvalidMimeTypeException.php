<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class InvalidMimeTypeException extends \Exception
{
    public function __construct(string $mimeType)
    {
        parent::__construct(\sprintf('MIME type "%s" is not allowed', $mimeType));
    }
}
