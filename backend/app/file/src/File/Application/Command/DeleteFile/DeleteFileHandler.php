<?php

declare(strict_types=1);

namespace App\File\File\Application\Command\DeleteFile;

use App\File\File\Domain\Exception\FileAccessDeniedException;
use App\File\File\Domain\Exception\FileNotFoundException;
use App\File\File\Domain\Exception\FileStorageException;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteFileHandler
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private FileStorageServiceInterface $fileStorageService,
    ) {
    }

    /**
     * @throws FileStorageException
     * @throws FileAccessDeniedException
     * @throws FileNotFoundException
     */
    public function __invoke(DeleteFileCommand $command): void
    {
        $file = $this->fileRepository->findById($command->fileId);

        if (null === $file) {
            throw new FileNotFoundException($command->fileId->asString());
        }

        if (!$file->isOwnedBy($command->uploaderId)) {
            throw new FileAccessDeniedException($command->fileId->asString());
        }

        $this->fileStorageService->delete($file->path());

        $file->delete();
        $this->fileRepository->save($file);
    }
}
