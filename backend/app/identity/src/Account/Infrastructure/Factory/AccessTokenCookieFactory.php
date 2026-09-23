<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Factory;

use App\Shared\Infrastructure\Security\JwtAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

readonly class AccessTokenCookieFactory
{
    public const string COOKIE_NAME = JwtAuthenticator::ACCESS_TOKEN_COOKIE_NAME;

    private const string COOKIE_PATH = '/api';

    private const int COOKIE_TTL_HOURS = 1;

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function create(string $accessToken): Cookie
    {
        return new Cookie(
            name: self::COOKIE_NAME,
            value: $accessToken,
            expire: new \DateTimeImmutable('+'.self::COOKIE_TTL_HOURS.' hours'),
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
