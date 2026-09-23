<?php

declare(strict_types=1);

namespace App\File\File\Application\Command\ProcessImage;

use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ProcessImageCommand implements CommandInterface
{
    public function __construct(
        public FileId $fileId,
        public string $originalPath,
        public string $mimeType,
    ) {
    }
}
