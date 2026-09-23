<?php

declare(strict_types=1);

namespace App\File\File\Application\Command\UploadFile;

use App\File\File\Application\Command\ProcessImage\ProcessImageCommand;
use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\Shared\Application\Command\Sync\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class UploadFileHandler implements CommandHandlerInterface
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private FileStorageServiceInterface $fileStorage,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(UploadFileCommand $command): void
    {
        $path = $this->fileStorage->store(
            $command->uploadedFile,
            $command->fileId,
            $command->fileName
        );

        $file = File::create(
            id: $command->fileId,
            name: $command->fileName,
            originalName: $command->originalName,
            mimeType: $command->mimeType,
            size: $command->size,
            path: $path,
            description: $command->description,
            uploaderId: $command->uploaderId,
            tenantId: $command->tenantId,
        );

        $this->fileRepository->save($file);

        if ($command->mimeType->isImage()) {
            $this->messageBus->dispatch(new ProcessImageCommand(
                fileId: $command->fileId,
                originalPath: $path,
                mimeType: $command->mimeType->asString(),
            ));
        }
    }
}
