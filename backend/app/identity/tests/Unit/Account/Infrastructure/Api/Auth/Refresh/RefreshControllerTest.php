<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\Refresh;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Infrastructure\Api\Auth\Refresh\RefreshController;
use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Identity\RefreshToken\Application\Command\Refresh\RefreshCommand;
use App\Shared\Infrastructure\Security\RateLimiter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(RefreshController::class)]
final class RefreshControllerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;

    private RefreshTokenCookieFactory&Stub $cookieFactory;

    private AccessTokenCookieFactory&Stub $accessTokenCookieFactory;

    private RateLimiter $rateLimiter;

    #[Test]
    public function returns_401_when_cookie_missing(): void
    {
        $controller = new RefreshController($this->cookieFactory, $this->accessTokenCookieFactory, $this->rateLimiter);
        $request = new Request();

        $this->bus->expects($this->never())->method('dispatch');

        $response = $controller($request, $this->bus);

        Assert::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    #[Test]
    public function dispatches_refresh_command_using_cookie_and_returns_jwt(): void
    {
        $refreshTokenValue = 'refresh_token_from_cookie';
        $newJwt = 'new_jwt_token';
        $newRefreshToken = 'new_refresh_token';

        $request = new Request(cookies: [RefreshTokenCookieFactory::COOKIE_NAME => $refreshTokenValue]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (RefreshCommand $command) use ($refreshTokenValue): bool {
                Assert::assertSame($refreshTokenValue, $command->refreshToken);

                return true;
            }))
            ->willReturnCallback(static fn ($command): Envelope => new Envelope(
                $command,
                [new HandledStamp(new TokensDto($newJwt, $newRefreshToken), 'handler')]
            ));

        $fakeCookie = Cookie::create(RefreshTokenCookieFactory::COOKIE_NAME, $newRefreshToken);
        $this->cookieFactory->method('create')->willReturn($fakeCookie);

        $fakeAccessCookie = Cookie::create(AccessTokenCookieFactory::COOKIE_NAME, $newJwt);
        $this->accessTokenCookieFactory->method('create')->willReturn($fakeAccessCookie);

        $controller = new RefreshController($this->cookieFactory, $this->accessTokenCookieFactory, $this->rateLimiter);
        $response = $controller($request, $this->bus);

        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame($newJwt, $data['token']['jwtToken'] ?? null);
        Assert::assertArrayNotHasKey('refreshToken', $data['token'] ?? []);
    }

    #[Test]
    public function returns_null_jwt_when_no_handled_stamp(): void
    {
        $request = new Request(cookies: [RefreshTokenCookieFactory::COOKIE_NAME => 'some_token']);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command));

        $controller = new RefreshController($this->cookieFactory, $this->accessTokenCookieFactory, $this->rateLimiter);
        $response = $controller($request, $this->bus);

        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('token', $data);
        Assert::assertSame('', $data['token']['jwtToken'] ?? null);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->cookieFactory = $this->createStub(RefreshTokenCookieFactory::class);
        $this->accessTokenCookieFactory = $this->createStub(AccessTokenCookieFactory::class);
        $this->rateLimiter = new RateLimiter(new ArrayAdapter());
    }
}
