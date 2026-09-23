<?php

declare(strict_types=1);

namespace App\File\File\Domain\Event;

use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\User\UserId;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('sync')]
final readonly class FileDeleted implements DomainEventInterface
{
    public function __construct(
        private FileId $fileId,
        private UserId $uploaderId,
    ) {
    }

    public function fileId(): FileId
    {
        return $this->fileId;
    }

    public function uploaderId(): UserId
    {
        return $this->uploaderId;
    }
}
