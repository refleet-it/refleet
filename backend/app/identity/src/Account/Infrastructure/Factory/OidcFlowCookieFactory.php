<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Factory;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Pins a sign-in flow to the browser that started it.
 *
 * A signed `state` proves this instance issued it, not that this browser asked for it. An
 * attacker can start a flow, complete it as themselves and hand the victim the resulting
 * `code` and `state`, and the victim ends up signed in to the attacker's account — the
 * classic login CSRF. Keeping the same state in an HttpOnly cookie and requiring the two to
 * match in the callback makes that fail, because the attacker cannot write the cookie into
 * the victim's browser.
 *
 * Lax rather than Strict: the callback is a same-site request from this instance's own
 * sign-in page, so Strict would also work for it, but Lax keeps the cookie readable if a
 * frontend ever handles the provider's redirect with a top-level navigation instead.
 *
 * TTL matches OidcStateSigner's: a cookie that outlives the state it carries only produces a
 * confusing error later.
 */
readonly class OidcFlowCookieFactory
{
    public const string COOKIE_NAME = 'oidc_flow';

    private const string COOKIE_PATH = '/api/identity/auth/oidc';

    private const int COOKIE_TTL_MINUTES = 10;

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function create(string $state): Cookie
    {
        return new Cookie(
            name: self::COOKIE_NAME,
            value: $state,
            expire: new \DateTimeImmutable('+'.self::COOKIE_TTL_MINUTES.' minutes'),
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
