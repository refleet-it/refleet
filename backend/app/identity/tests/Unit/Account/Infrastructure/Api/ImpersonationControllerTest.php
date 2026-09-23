<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api;

use App\Identity\Account\Application\Command\Impersonate\ImpersonateCommand;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Infrastructure\Api\ImpersonationController;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ImpersonationController::class)]
final class ImpersonationControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private RefreshTokenCookieFactory&MockObject $cookieFactory;

    #[Test]
    public function dispatches_impersonate_command_and_returns_token_json_with_cookie(): void
    {
        // Arrange
        $targetAccountId = '11111111-2222-3333-4444-555555555555';
        $adminUserId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $user = new AccountUser('admin@example.com', ['ROLE_ADMINISTRATOR'], null, $adminUserId);

        $expectedJwt = 'jwt-token-123';
        $expectedRefresh = 'refresh-token-456';

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $command) use ($targetAccountId, $adminUserId): bool {
                Assert::assertInstanceOf(ImpersonateCommand::class, $command);
                Assert::assertSame($targetAccountId, $command->targetAccountId->asString());
                Assert::assertSame($adminUserId->asString(), $command->adminAccountId->asString());

                return true;
            }))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope($command, [
                new HandledStamp(new TokensDto(jwtToken: $expectedJwt, refreshToken: $expectedRefresh), 'handler'),
            ]));

        $fakeCookie = Cookie::create(RefreshTokenCookieFactory::COOKIE_NAME, $expectedRefresh);
        $this->cookieFactory
            ->expects($this->once())
            ->method('create')
            ->with($expectedRefresh)
            ->willReturn($fakeCookie);

        $controller = new ImpersonationController($this->bus, $this->cookieFactory);

        // Act
        $response = $controller(
            targetAccountId: $targetAccountId,
            user: $user,
        );

        // Assert
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame($expectedJwt, $data['token'] ?? null);
        Assert::assertArrayNotHasKey('refreshToken', $data);

        $cookies = $response->headers->getCookies();
        Assert::assertCount(1, $cookies);
        Assert::assertSame(RefreshTokenCookieFactory::COOKIE_NAME, $cookies[0]->getName());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_runtime_exception_when_command_not_handled(): void
    {
        // Arrange
        $targetAccountId = '99999999-8888-7777-6666-555555555555';
        $adminUserId = UserId::fromString('12121212-3434-5656-7878-909090909090');
        $user = new AccountUser('admin2@example.com', ['ROLE_ADMINISTRATOR'], null, $adminUserId);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ImpersonateCommand::class))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope($command)); // No HandledStamp

        $controller = new ImpersonationController($this->bus, $this->cookieFactory);

        // Assert exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command not handled');

        // Act
        $controller(
            targetAccountId: $targetAccountId,
            user: $user,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->cookieFactory = $this->createMock(RefreshTokenCookieFactory::class);
    }
}
