<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class MimeTypeCannotBeEmptyException extends \Exception
{
    public function __construct()
    {
        parent::__construct('MIME type cannot be empty');
    }
}
