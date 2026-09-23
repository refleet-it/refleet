<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\Login;

use App\Identity\Account\Application\Command\Login\LoginCommand;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Infrastructure\Api\Auth\Login\LoginController;
use App\Identity\Account\Infrastructure\Api\Auth\Login\LoginRequest;
use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(LoginController::class)]
final class LoginControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private RefreshTokenCookieFactory&Stub $cookieFactory;

    private AccessTokenCookieFactory&Stub $accessTokenCookieFactory;

    private CacheItemPoolInterface&Stub $cache;

    #[Test]
    public function dispatches_login_command_and_returns_jwt_without_refresh_token_in_body(): void
    {
        $payload = new LoginRequest(
            email: 'user@example.com',
            password: 'password123',
        );
        $tokensDto = new TokensDto(
            jwtToken: 'jwt_token_abc123',
            refreshToken: 'refresh_token_xyz',
        );

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $command) use ($payload): bool {
                Assert::assertInstanceOf(LoginCommand::class, $command);
                Assert::assertSame($payload->email, $command->email);
                Assert::assertSame($payload->password, $command->plainPassword);

                return true;
            }))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope($command, [new HandledStamp($tokensDto, 'handler')]));

        $fakeCookie = Cookie::create(RefreshTokenCookieFactory::COOKIE_NAME, 'refresh_token_xyz');
        $this->cookieFactory->method('create')->willReturn($fakeCookie);

        $fakeAccessCookie = Cookie::create(AccessTokenCookieFactory::COOKIE_NAME, 'jwt_token_abc123');
        $this->accessTokenCookieFactory->method('create')->willReturn($fakeAccessCookie);

        $controller = new LoginController($this->cookieFactory, $this->accessTokenCookieFactory, $this->cache);
        $response = $controller(payload: $payload, bus: $this->bus, request: new Request());

        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(200, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('jwt_token_abc123', $data['token']['jwtToken'] ?? null);
        Assert::assertArrayNotHasKey('refreshToken', $data['token'] ?? []);
    }

    #[Test]
    public function returns_empty_jwt_when_no_handled_stamp(): void
    {
        $payload = new LoginRequest(
            email: 'user2@example.com',
            password: 'anotherPass123',
        );

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LoginCommand::class))
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command));

        $controller = new LoginController($this->cookieFactory, $this->accessTokenCookieFactory, $this->cache);
        $response = $controller(payload: $payload, bus: $this->bus, request: new Request());

        Assert::assertSame(200, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('token', $data);
        Assert::assertArrayNotHasKey('refreshToken', $data['token'] ?? []);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->cookieFactory = $this->createStub(RefreshTokenCookieFactory::class);
        $this->accessTokenCookieFactory = $this->createStub(AccessTokenCookieFactory::class);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache = $this->createStub(CacheItemPoolInterface::class);
        $this->cache->method('getItem')->willReturn($cacheItem);
        $this->cache->method('deleteItem')->willReturn(true);
    }
}
