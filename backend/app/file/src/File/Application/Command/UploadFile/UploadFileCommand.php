<?php

declare(strict_types=1);

namespace App\File\File\Application\Command\UploadFile;

use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\Shared\Application\Command\Sync\CommandInterface;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class UploadFileCommand implements CommandInterface
{
    public function __construct(
        public FileId $fileId,
        public UploadedFile $uploadedFile,
        public FileName $fileName,
        public FileName $originalName,
        public MimeType $mimeType,
        public FileSize $size,
        public ?string $description,
        public UserId $uploaderId,
        public ?TenantId $tenantId,
    ) {
    }
}
