<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\RequestPasswordReset;

use App\Identity\Account\Application\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\Identity\Account\Infrastructure\Api\Auth\RequestPasswordReset\RequestPasswordResetController;
use App\Identity\Account\Infrastructure\Api\Auth\RequestPasswordReset\RequestPasswordResetRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(RequestPasswordResetController::class)]
final class RequestPasswordResetControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private CacheItemPoolInterface&Stub $cache;

    #[Test]
    public function dispatches_request_password_reset_command_and_returns_confirmation_message(): void
    {
        // Arrange
        $payload = new RequestPasswordResetRequest(
            email: 'user@example.com',
        );

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $command) use ($payload): bool {
                Assert::assertInstanceOf(RequestPasswordResetCommand::class, $command);
                Assert::assertSame($payload->email, $command->email);

                return true;
            }))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope($command));

        $controller = new RequestPasswordResetController($this->cache);

        // Act
        $response = $controller(
            payload: $payload,
            bus: $this->bus,
        );

        // Assert
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame(
            'If an account with this email exists, a password reset link has been sent.',
            $data['message'] ?? null,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache = $this->createStub(CacheItemPoolInterface::class);
        $this->cache->method('getItem')->willReturn($cacheItem);
        $this->cache->method('save')->willReturn(true);
    }
}
