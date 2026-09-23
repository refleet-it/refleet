<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class FileSizeCannotBeNegativeException extends \Exception
{
    public function __construct()
    {
        parent::__construct('File size cannot be negative');
    }
}
