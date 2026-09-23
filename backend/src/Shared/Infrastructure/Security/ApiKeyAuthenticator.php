<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Service\ApiKeyValidatorInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Long-lived API keys for machine clients (e.g. the product's MCP server acting on a
 * user's behalf) - distinct from JwtAuthenticator's short-lived, browser-session JWTs.
 * Resolves to the same AccountUser, so ProjectAccessVoter/#[IsGranted]/#[CurrentUser] work
 * unchanged regardless of which authenticator handled the request. Delegates the actual
 * lookup to ApiKeyValidatorInterface so this class runs in every context's container:
 * Identity implements it directly against its own database, every other context through an
 * internal HTTP call — see docs/adr/0001-multiple-kernels.md.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public const string PREFIX = 'ib_';

    public function __construct(
        private readonly ApiKeyValidatorInterface $apiKeyValidator,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        $authHeader = $request->headers->get('Authorization');

        return \is_string($authHeader) && \str_starts_with($authHeader, 'Bearer '.self::PREFIX);
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization') ?? '';
        $token = \substr($authHeader, 7);

        $validated = $this->apiKeyValidator->validate($token);

        if (null === $validated) {
            throw new CustomUserMessageAuthenticationException('Invalid or revoked API key');
        }

        return new SelfValidatingPassport(
            new UserBadge($validated->email, static fn () => new AccountUser(
                $validated->email,
                $validated->symfonyRoles,
                null,
                UserId::fromString($validated->accountId),
                apiKeyId: $validated->apiKeyId,
            ))
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
