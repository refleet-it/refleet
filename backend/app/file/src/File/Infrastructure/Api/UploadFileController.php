<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Api;

use App\File\File\Application\Command\UploadFile\UploadFileCommand;
use App\File\File\Domain\Exception\FileStorageException;
use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/files/upload', name: 'file_upload', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[OA\Post(
    description: 'Upload a file',
    summary: 'Upload File',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'businessId', type: 'string', format: 'uuid'),
                ],
                type: 'object'
            )
        )
    ),
    tags: ['File File'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'File uploaded successfully'),
        new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid file'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Unauthorized'),
    ]
)]
final readonly class UploadFileController
{
    public function __construct(
        private MessageBusInterface $bus,
        private FileStorageServiceInterface $fileStorageService,
        private FileRepositoryInterface $fileRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        Request $request,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('file');

        $validationError = $this->validateUploadedFile($uploadedFile);
        if (null !== $validationError) {
            return $validationError;
        }

        \assert($uploadedFile instanceof UploadedFile);

        try {
            $command = $this->buildUploadCommand($uploadedFile, $request, $user);
            $this->bus->dispatch($command);

            return $this->buildUploadResponse($command);
        } catch (FileStorageException $exception) {
            $this->logger->error('File storage error during upload', [
                'error' => $exception->getMessage(),
                'uploader' => $user->getUserId()->asString(),
            ]);

            return new JsonResponse(['error' => 'File storage error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $exception) {
            $this->logger->error('Unexpected error during file upload', [
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
                'uploader' => $user->getUserId()->asString(),
            ]);

            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function buildUploadCommand(UploadedFile $uploadedFile, Request $request, AccountUser $user): UploadFileCommand
    {
        $mimeType = $uploadedFile->getMimeType();
        $description = $request->request->get('description');
        $description = null === $description ? null : (string) $description;

        return new UploadFileCommand(
            fileId: FileId::generate(),
            uploadedFile: $uploadedFile,
            fileName: FileName::fromString($this->generateUniqueFileName($uploadedFile)),
            originalName: FileName::fromString($uploadedFile->getClientOriginalName()),
            mimeType: MimeType::fromString((string) $mimeType),
            size: FileSize::fromBytes($uploadedFile->getSize()),
            description: $description,
            uploaderId: $user->getUserId(),
            tenantId: null,
        );
    }

    private function validateUploadedFile(?UploadedFile $uploadedFile): ?JsonResponse
    {
        if (null === $uploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        if (false === $uploadedFile->isValid()) {
            return new JsonResponse(['error' => 'Invalid file upload'], Response::HTTP_BAD_REQUEST);
        }

        if (null === $uploadedFile->getMimeType()) {
            return new JsonResponse(['error' => 'Invalid file type'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    private function buildUploadResponse(UploadFileCommand $command): JsonResponse
    {
        $file = $this->fileRepository->findById($command->fileId);
        if (null === $file) {
            return new JsonResponse(['error' => 'File not found after upload'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $filePath = $file->path();
        if (\str_starts_with($filePath, 'uploads/')) {
            $filePath = \substr($filePath, 8);
        }

        $fileUrl = $this->fileStorageService->getFileUrl($filePath);
        $thumbnailUrl = $this->resolveThumbnailUrl($file);

        $response = new UploadFileResponse(
            id: $command->fileId->asString(),
            name: $command->fileName->asString(),
            originalName: $command->originalName->asString(),
            mimeType: $command->mimeType->asString(),
            size: $command->size->asBytes(),
            sizeFormatted: $command->size->getFormattedSize(),
            description: $command->description,
            uploaderId: $command->uploaderId->asString(),
            tenantId: $command->tenantId?->asString(),
            url: $fileUrl,
            thumbnailUrl: $thumbnailUrl,
            webpUrl: $fileUrl,
            webpThumbnailUrl: $thumbnailUrl,
        );

        return new JsonResponse([
            'id' => $response->id,
            'name' => $response->name,
            'originalName' => $response->originalName,
            'mimeType' => $response->mimeType,
            'size' => $response->size,
            'sizeFormatted' => $response->sizeFormatted,
            'description' => $response->description,
            'uploaderId' => $response->uploaderId,
            'tenantId' => $response->tenantId,
            'url' => $response->url,
            'thumbnailUrl' => $response->thumbnailUrl,
            'webpUrl' => $response->webpUrl,
            'webpThumbnailUrl' => $response->webpThumbnailUrl,
        ], Response::HTTP_CREATED);
    }

    private function resolveThumbnailUrl(File $file): ?string
    {
        $thumbnailPath = $file->thumbnailPath();
        if (!$file->mimeType()->isImage() || null === $thumbnailPath) {
            return null;
        }

        return $this->fileStorageService->getFileUrl($thumbnailPath);
    }

    private function generateUniqueFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $extension = '' !== $extension ? '.'.$extension : '';

        return 'file_'.\bin2hex(\random_bytes(16)).$extension;
    }
}
