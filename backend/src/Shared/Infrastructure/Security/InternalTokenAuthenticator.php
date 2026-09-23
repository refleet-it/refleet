<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Gates container-to-container calls under /internal — InternalApiClient sends this header
 * when a context calls another context's HTTP-backed adapter for a Shared\Domain\Service
 * port (see that class). A shared secret, not a user identity: there is no human or account
 * on either side of this call, only two containers. Not reachable from a public domain by
 * design — see docs/adr/0001-multiple-kernels.md.
 */
final class InternalTokenAuthenticator extends AbstractAuthenticator
{
    public const string HEADER = 'X-Internal-Token';

    private readonly string $expectedToken;

    public function __construct()
    {
        $token = $_ENV['INTERNAL_SERVICE_TOKEN'] ?? throw new \InvalidArgumentException('INTERNAL_SERVICE_TOKEN environment variable is not set');
        if (!\is_string($token) || '' === $token) {
            throw new \InvalidArgumentException('INTERNAL_SERVICE_TOKEN must be a non-empty string');
        }

        $this->expectedToken = $token;
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return $request->headers->has(self::HEADER);
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $provided = (string) $request->headers->get(self::HEADER, '');

        if ('' === $provided || !\hash_equals($this->expectedToken, $provided)) {
            throw new CustomUserMessageAuthenticationException('Invalid internal service token');
        }

        return new SelfValidatingPassport(
            new UserBadge('internal-service', static fn () => new InMemoryUser('internal-service', null, ['ROLE_INTERNAL_SERVICE']))
        );
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
