<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class FileNameCannotBeEmptyException extends \Exception
{
    public function __construct()
    {
        parent::__construct('File name cannot be empty');
    }
}
