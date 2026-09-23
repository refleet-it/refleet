<?php

declare(strict_types=1);

namespace App\File\File\Application\Command\DeleteFile;

use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Application\Command\Sync\CommandInterface;
use App\Shared\Domain\User\UserId;

final readonly class DeleteFileCommand implements CommandInterface
{
    public function __construct(
        public FileId $fileId,
        public UserId $uploaderId,
    ) {
    }
}
