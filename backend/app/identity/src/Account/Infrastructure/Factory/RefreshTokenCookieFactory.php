<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Factory;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

readonly class RefreshTokenCookieFactory
{
    public const string COOKIE_NAME = 'refresh_token';

    private const string COOKIE_PATH = '/api/identity';

    private const int COOKIE_TTL_DAYS = 7;

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function create(string $refreshToken): Cookie
    {
        return new Cookie(
            name: self::COOKIE_NAME,
            value: $refreshToken,
            expire: new \DateTimeImmutable('+'.self::COOKIE_TTL_DAYS.' days'),
            path: self::COOKIE_PATH,
            secure: 'prod' === $this->environment,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    public function createClear(): Cookie
    {
        return new Cookie(
            name: self::COOKIE_NAME,
            value: '',
            expire: new \DateTimeImmutable('-1 day'),
            path: self::COOKIE_PATH,
            secure: 'prod' === $this->environment,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
