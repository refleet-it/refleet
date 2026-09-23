<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\Register;

use App\Identity\Account\Application\Command\Register\RegisterCommand;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Infrastructure\Api\Auth\Register\RegisterController;
use App\Identity\Account\Infrastructure\Api\Auth\Register\RegisterRequest;
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

#[CoversClass(RegisterController::class)]
final class RegisterControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private RateLimiter $rateLimiter;

    #[Test]
    public function dispatches_register_command_and_returns_created_with_token(): void
    {
        // Arrange
        $payload = new RegisterRequest(
            email: 'new.user@example.com',
            password: TestCredentialsGenerator::password(),
            termsAccepted: true,
        );

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $command) use ($payload): bool {
                Assert::assertInstanceOf(RegisterCommand::class, $command);
                Assert::assertSame($payload->email, $command->email);
                Assert::assertSame($payload->password, $command->plainPassword);
                Assert::assertSame(RoleEnum::USER, $command->role);
                Assert::assertTrue($command->termsAccepted);

                return true;
            }))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope($command));

        $controller = new RegisterController($this->rateLimiter, true);

        // Act
        $response = $controller(
            payload: $payload,
            bus: $this->bus,
            request: new Request(),
        );

        // Assert
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('message', $data);
        Assert::assertSame('Account created successfully. Please check your email to verify your account.', $data['message']);
    }

    #[Test]
    public function refuses_registration_and_dispatches_nothing_when_the_instance_is_closed(): void
    {
        // Arrange
        $payload = new RegisterRequest(
            email: 'someone@example.com',
            password: 'SecurePassword123!',
            termsAccepted: true,
        );

        $this->bus->expects($this->never())->method('dispatch');

        $controller = new RegisterController($this->rateLimiter, false);

        // Act
        $response = $controller(
            payload: $payload,
            bus: $this->bus,
            request: new Request(),
        );

        // Assert
        Assert::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function always_dispatches_user_role_regardless_of_request_payload(): void
    {
        // Arrange
        $payload = new RegisterRequest(
            email: 'another.user@example.com',
            password: 'AnotherStrongPass123',
            termsAccepted: true,
        );

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $command): bool {
                Assert::assertInstanceOf(RegisterCommand::class, $command);
                Assert::assertSame(RoleEnum::USER, $command->role);

                return true;
            }))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope($command));

        $controller = new RegisterController($this->rateLimiter, true);

        // Act
        $response = $controller(
            payload: $payload,
            bus: $this->bus,
            request: new Request(),
        );

        // Assert
        Assert::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('message', $data);
        Assert::assertSame('Account created successfully. Please check your email to verify your account.', $data['message']);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->rateLimiter = new RateLimiter(new ArrayAdapter());
    }
}
