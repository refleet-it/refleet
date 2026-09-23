<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Infrastructure\Api;

use App\File\File\Application\Command\DeleteFile\DeleteFileCommand;
use App\File\File\Domain\Exception\FileAccessDeniedException;
use App\File\File\Domain\Exception\FileNotFoundException;
use App\File\File\Infrastructure\Api\DeleteFileController;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(DeleteFileController::class)]
final class DeleteFileControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private AccountUser $user;

    private string $userId;

    #[Test]
    public function dispatches_delete_command_and_returns_success_response(): void
    {
        // Arrange
        $fileId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (object $command) use ($fileId): bool {
                Assert::assertInstanceOf(DeleteFileCommand::class, $command);
                Assert::assertSame($fileId, $command->fileId->asString());
                Assert::assertSame($this->userId, $command->uploaderId->asString());

                return true;
            }))
            ->willReturnCallback(static fn ($message): Envelope => new Envelope($message));

        $controller = new DeleteFileController($this->bus);

        // Act
        $response = $controller($fileId, $this->user);

        // Assert
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('File deleted successfully', $payload['message'] ?? null);
        Assert::assertSame($fileId, $payload['fileId'] ?? null);
    }

    #[Test]
    public function returns_404_when_file_not_found(): void
    {
        // Arrange
        $fileId = 'ffffffff-eeee-dddd-cccc-bbbbbbbbbbbb';

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new FileNotFoundException($fileId));

        $controller = new DeleteFileController($this->bus);

        // Act
        $response = $controller($fileId, $this->user);

        // Assert
        Assert::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('File not found', $payload['error'] ?? null);
    }

    #[Test]
    public function returns_403_when_access_denied(): void
    {
        // Arrange
        $fileId = '11111111-2222-3333-4444-555555555555';

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new FileAccessDeniedException($fileId));

        $controller = new DeleteFileController($this->bus);

        // Act
        $response = $controller($fileId, $this->user);

        // Assert
        Assert::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Access denied', $payload['error'] ?? null);
    }

    #[Test]
    public function returns_500_on_unexpected_exception(): void
    {
        // Arrange
        $fileId = '99999999-8888-7777-6666-555555555555';

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('boom'));

        $controller = new DeleteFileController($this->bus);

        // Act
        $response = $controller($fileId, $this->user);

        // Assert
        Assert::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $payload = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Internal server error', $payload['error'] ?? null);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->userId = '12345678-90ab-cdef-1234-567890abcdef';
        $this->user = new AccountUser(
            'user@example.com',
            ['ROLE_USER'],
            null,
            UserId::fromString($this->userId),
        );
    }
}
