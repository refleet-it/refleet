<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\ResetPassword;

use App\Identity\Account\Application\Command\ResetPassword\ResetPasswordCommand;
use App\Identity\Account\Infrastructure\Api\Auth\ResetPassword\ResetPasswordController;
use App\Identity\Account\Infrastructure\Api\Auth\ResetPassword\ResetPasswordRequest;
use App\Shared\Domain\Exception\TooManyRequestsException;
use App\Shared\Infrastructure\Security\RateLimiter;
use App\Tests\Helpers\TestCredentialsGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ResetPasswordController::class)]
final class ResetPasswordControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private RateLimiter $rateLimiter;

    #[Test]
    public function dispatches_reset_password_and_returns_success_message(): void
    {
        // Arrange
        $newPassword = TestCredentialsGenerator::password();
        $payload = new ResetPasswordRequest(
            token: 'reset-token-123',
            newPassword: $newPassword,
        );

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $command) use ($payload): bool {
                Assert::assertInstanceOf(ResetPasswordCommand::class, $command);
                Assert::assertSame($payload->token, $command->token);
                Assert::assertSame($payload->newPassword, $command->newPassword);

                return true;
            }))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope($command));

        $controller = new ResetPasswordController($this->rateLimiter);

        // Act
        $response = $controller(
            payload: $payload,
            bus: $this->bus,
            request: new Request(),
        );

        // Assert
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Password has been successfully reset.', $data['message'] ?? null);
    }

    #[Test]
    public function throttles_after_max_attempts_from_same_ip(): void
    {
        // Arrange
        $payload = new ResetPasswordRequest(
            token: 'reset-token-123',
            newPassword: TestCredentialsGenerator::password(),
        );
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);

        $this->bus
            ->expects($this->exactly(10))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command));

        $controller = new ResetPasswordController($this->rateLimiter);

        // Act: exhaust the allowed attempts for this IP
        for ($i = 0; $i < 10; ++$i) {
            $controller(payload: $payload, bus: $this->bus, request: $request);
        }

        // Assert
        $this->expectException(TooManyRequestsException::class);
        $controller(payload: $payload, bus: $this->bus, request: $request);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->rateLimiter = new RateLimiter(new ArrayAdapter());
    }
}
