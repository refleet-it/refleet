<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Api;

use App\File\File\Domain\Repository\FileRepositoryInterface;
use App\File\File\Domain\Service\FileStorageServiceInterface;
use App\Shared\Domain\User\AccountUser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/storage/{bucket}/{path}', name: 'storage_proxy', requirements: ['path' => '.+'], methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final readonly class StorageProxyController
{
    private const int CACHE_MAX_AGE_SECONDS = 31536000; // 1 year

    public function __construct(
        private FileStorageServiceInterface $fileStorageService,
        private FileRepositoryInterface $fileRepository,
    ) {
    }

    public function __invoke(
        string $bucket,
        string $path,
        #[CurrentUser]
        AccountUser $user,
    ): Response {
        $file = $this->fileRepository->findByPath($path);

        if (null === $file || !$file->isOwnedBy($user->getUserId())) {
            throw new NotFoundHttpException('File not found');
        }

        try {
            $stream = $this->fileStorageService->getStream($path);

            if (null === $stream) {
                throw new NotFoundHttpException('File not found');
            }

            $mimeType = $this->guessMimeType($path);

            $response = new StreamedResponse(static function () use ($stream): void {
                if (\is_resource($stream)) {
                    \fpassthru($stream);
                    \fclose($stream);
                }
            });

            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('Cache-Control', 'public, max-age='.self::CACHE_MAX_AGE_SECONDS);

            return $response;
        } catch (\Exception $exception) {
            throw new NotFoundHttpException('File not found: '.$exception->getMessage(), $exception);
        }
    }

    private function guessMimeType(string $path): string
    {
        $extension = \strtolower(\pathinfo($path, \PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
