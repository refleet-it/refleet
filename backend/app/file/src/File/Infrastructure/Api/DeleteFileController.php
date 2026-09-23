<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Api;

use App\File\File\Application\Command\DeleteFile\DeleteFileCommand;
use App\File\File\Domain\Exception\FileAccessDeniedException;
use App\File\File\Domain\Exception\FileNotFoundException;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/files/{fileId}', name: 'file_delete', requirements: ['fileId' => Requirements::UUID], methods: ['DELETE'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[OA\Delete(
    description: 'Delete a file by ID',
    summary: 'Delete File',
    tags: ['File File'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'File deleted successfully'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'File not found'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Access denied'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Unauthorized'),
    ]
)]
final readonly class DeleteFileController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        string $fileId,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        try {
            $command = new DeleteFileCommand(
                fileId: FileId::fromString($fileId),
                uploaderId: $user->getUserId(),
            );

            $this->bus->dispatch($command);

            return new JsonResponse([
                'message' => 'File deleted successfully',
                'fileId' => $fileId,
            ], Response::HTTP_OK);
        } catch (FileNotFoundException) {
            return new JsonResponse([
                'error' => 'File not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (FileAccessDeniedException) {
            return new JsonResponse([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception) {
            return new JsonResponse([
                'error' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
