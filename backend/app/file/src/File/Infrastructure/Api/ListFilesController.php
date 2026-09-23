<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Api;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/files', name: 'files_list', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[OA\Get(
    description: 'List files uploaded by the currently authenticated user.',
    summary: 'List My Files',
    tags: ['File File'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'List of files'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Unauthorized'),
    ]
)]
final readonly class ListFilesController
{
    private const string UPLOADS_PATH_PREFIX = 'uploads/';

    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private FileStorageServiceInterface $fileStorageService,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $files = $this->fileRepository->findActiveByUploaderId($user->getUserId());

        $filesData = \array_map(function (File $file): array {
            $relativePath = \substr($file->path(), \strlen(self::UPLOADS_PATH_PREFIX));

            return [
                'id' => $file->id()->asString(),
                'name' => $file->name()->asString(),
                'originalName' => $file->originalName()->asString(),
                'mimeType' => $file->mimeType()->asString(),
                'size' => $file->size()->asBytes(),
                'sizeFormatted' => $file->size()->getFormattedSize(),
                'description' => $file->description(),
                'uploaderId' => $file->uploaderId()->asString(),
                'tenantId' => $file->tenantId()?->asString(),
                'createdAt' => $file->createdAt()->format('c'),
                'updatedAt' => $file->updatedAt()->format('c'),
                'url' => $this->fileStorageService->getFileUrl($relativePath),
                'thumbnailUrl' => $file->mimeType()->isImage() ? $this->fileStorageService->getThumbnailUrl($relativePath) : null,
                'isImage' => $file->mimeType()->isImage(),
                'isDocument' => $file->mimeType()->isDocument(),
                'isArchive' => $file->mimeType()->isArchive(),
            ];
        }, $files);

        return new JsonResponse([
            'files' => $filesData,
            'count' => \count($filesData),
        ], Response::HTTP_OK);
    }
}
