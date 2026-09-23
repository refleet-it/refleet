<?php

declare(strict_types=1);

namespace App\File\File\Domain\Exception;

final class InvalidFileNameException extends \Exception
{
    public function __construct()
    {
        parent::__construct('File name contains invalid characters. Polish characters, spaces, dots, underscores and hyphens are allowed. Special characters like < > : " / \\ | ? * and control characters are not allowed.');
    }
}
