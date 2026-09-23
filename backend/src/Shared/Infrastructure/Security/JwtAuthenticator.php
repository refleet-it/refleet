<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\UserId;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
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
 * Verifies short-lived access tokens issued by Identity (see LcobucciTokenGenerator).
 * Self-contained by design: email, role and active-status all travel as claims, so this
 * authenticates requests in every context's container without a synchronous call back to
 * Identity's database on every request — see docs/adr/0001-multiple-kernels.md.
 * Deactivating an account therefore takes effect at the token's own expiry, not
 * immediately; that tradeoff is what buys the independence.
 *
 * Signature is asymmetric (RS256): only Identity's container holds JWT_PRIVATE_KEY. Every
 * other context, including this one, only ever sees JWT_PUBLIC_KEY, so it can verify a
 * token but never forge one.
 */
final class JwtAuthenticator extends AbstractAuthenticator
{
    public const string ACCESS_TOKEN_COOKIE_NAME = 'access_token';

    private readonly Configuration $config;

    public function __construct()
    {
        $publicKey = $_ENV['JWT_PUBLIC_KEY'] ?? throw new \InvalidArgumentException('JWT_PUBLIC_KEY environment variable is not set');
        if (!\is_string($publicKey) || '' === $publicKey) {
            throw new \InvalidArgumentException('JWT_PUBLIC_KEY must be a non-empty string');
        }

        // The signing-key slot is never used (this class only verifies, never builds a
        // token) — the public key is passed there too since Key must be non-empty.
        $this->config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($publicKey),
            InMemory::base64Encoded($publicKey),
        );
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        $authHeader = $request->headers->get('Authorization');
        if (\is_string($authHeader) && \str_starts_with($authHeader, 'Bearer ')) {
            // Bearer <ApiKeyAuthenticator::PREFIX>... belongs to ApiKeyAuthenticator, not us.
            return !\str_starts_with($authHeader, 'Bearer '.ApiKeyAuthenticator::PREFIX);
        }

        $cookieToken = $request->cookies->get(self::ACCESS_TOKEN_COOKIE_NAME);

        return \is_string($cookieToken) && '' !== $cookieToken;
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);
        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Missing or invalid JWT token');
        }

        try {
            return $this->validateTokenAndBuildPassport($token);
        } catch (\Exception $exception) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT token: '.$exception->getMessage(), [], 0, $exception);
        }
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // If only the httpOnly cookie triggered authentication (no explicit Authorization header),
        // return null so access_control decides. PUBLIC_ACCESS routes (login, refresh) will proceed;
        // protected routes will be denied by access_control as if no credentials were provided.
        $authHeader = $request->headers->get('Authorization');
        if (!\is_string($authHeader) || !\str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function extractToken(Request $request): string
    {
        $authHeader = $request->headers->get('Authorization');
        if (\is_string($authHeader) && \str_starts_with($authHeader, 'Bearer ')) {
            return \substr($authHeader, 7);
        }

        $cookieToken = $request->cookies->get(self::ACCESS_TOKEN_COOKIE_NAME);

        return \is_string($cookieToken) ? $cookieToken : '';
    }

    /** @param non-empty-string $token */
    private function validateTokenAndBuildPassport(string $token): Passport
    {
        $parsedToken = $this->config->parser()->parse($token);
        $this->assertTokenIsValid($parsedToken);

        /** @var \Lcobucci\JWT\Token\Plain $parsedToken */
        $accountId = $parsedToken->claims()->get('sub');
        if (!\is_string($accountId) || '' === $accountId) {
            throw new CustomUserMessageAuthenticationException('Token missing subject claim');
        }

        $email = $parsedToken->claims()->get('email');
        if (!\is_string($email) || '' === $email) {
            throw new CustomUserMessageAuthenticationException('Token missing email claim');
        }

        $role = $parsedToken->claims()->get('role');
        if (!\is_string($role) || '' === $role) {
            throw new CustomUserMessageAuthenticationException('Token missing role claim');
        }

        if (true !== $parsedToken->claims()->get('active')) {
            throw new CustomUserMessageAuthenticationException('Account is not active');
        }

        $impersonatorId = $parsedToken->claims()->get('impersonatorId');

        return new SelfValidatingPassport(
            new UserBadge($email, static fn () => new AccountUser(
                $email,
                [$role],
                null,
                UserId::fromString($accountId),
                \is_string($impersonatorId) ? $impersonatorId : null,
            ))
        );
    }

    private function assertTokenIsValid(\Lcobucci\JWT\Token $parsedToken): void
    {
        $constraint = new SignedWith($this->config->signer(), $this->config->verificationKey());
        if (!$this->config->validator()->validate($parsedToken, $constraint)) {
            throw new CustomUserMessageAuthenticationException('Invalid token signature');
        }

        if ($parsedToken->isExpired(new \DateTimeImmutable())) {
            throw new CustomUserMessageAuthenticationException('Token has expired');
        }
    }
}
