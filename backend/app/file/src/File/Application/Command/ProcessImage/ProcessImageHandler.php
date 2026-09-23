<?php

declare(strict_types=1);

namespace App\File\File\Application\Command\ProcessImage;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\WebPImageStorageServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessImageHandler
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private WebPImageStorageServiceInterface $storageService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessImageCommand $command): void
    {
        try {
            $this->logger->info('Processing image for WebP conversion', [
                'fileId' => $command->fileId->asString(),
                'path' => $command->originalPath,
            ]);

            $webpPath = $this->storageService->convertToWebP(
                $command->originalPath,
                $command->mimeType
            );

            $webpThumbnailPath = $this->storageService->createWebPThumbnail(
                $command->originalPath,
                $command->mimeType
            );

            if ($this->updateFileRecord($command, $webpPath, $webpThumbnailPath)) {
                $this->deleteOriginalIfReplaced($command->originalPath, $webpPath);
            }
        } catch (\Exception $exception) {
            $this->logger->error('Failed to process image', [
                'fileId' => $command->fileId->asString(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    private function updateFileRecord(ProcessImageCommand $command, ?string $webpPath, ?string $webpThumbnailPath): bool
    {
        /** @var File|null $file */
        $file = $this->fileRepository->findById($command->fileId);

        if (null === $file) {
            $this->logger->warning('File not found for WebP processing', [
                'fileId' => $command->fileId->asString(),
            ]);

            return false;
        }

        if (null !== $webpPath) {
            $file->updatePath($webpPath);
        }

        if (null !== $webpThumbnailPath) {
            $file->setThumbnailPath($webpThumbnailPath);
        }

        $this->fileRepository->save($file);

        $this->logger->info('Image processing completed', [
            'fileId' => $command->fileId->asString(),
            'webpPath' => $webpPath,
            'webpThumbnailPath' => $webpThumbnailPath,
        ]);

        return true;
    }

    private function deleteOriginalIfReplaced(string $originalPath, ?string $webpPath): void
    {
        if (null === $webpPath || $webpPath === $originalPath) {
            return;
        }

        try {
            $this->storageService->delete($originalPath);
            $this->logger->info('Deleted original file after WebP conversion', [
                'originalPath' => $originalPath,
            ]);
        } catch (\Exception $exception) {
            $this->logger->warning('Failed to delete original file', [
                'originalPath' => $originalPath,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
