<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Security\JwtAuthenticator;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

#[CoversClass(JwtAuthenticator::class)]
final class JwtAuthenticatorTest extends TestCase
{
    private const string ACCOUNT_ID = '11111111-1111-4111-8111-111111111111';

    private JwtAuthenticator $authenticator;

    private string $testPrivateKeyBase64;

    private mixed $previousJwtPublicKey;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function supports_returns_true_only_for_bearer_authorization_header_or_cookie(): void
    {
        // Arrange
        $bearerRequest = new Request();
        $bearerRequest->headers->set('Authorization', 'Bearer token');

        $apiKeyBearerRequest = new Request();
        $apiKeyBearerRequest->headers->set('Authorization', 'Bearer ib_some-api-key');

        $cookieRequest = new Request();
        $cookieRequest->cookies->set(JwtAuthenticator::ACCESS_TOKEN_COOKIE_NAME, 'token');

        $missingRequest = new Request();

        // Assert
        Assert::assertTrue($this->authenticator->supports($bearerRequest));
        Assert::assertFalse($this->authenticator->supports($apiKeyBearerRequest));
        Assert::assertTrue($this->authenticator->supports($cookieRequest));
        Assert::assertFalse($this->authenticator->supports($missingRequest));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function authenticate_throws_for_missing_or_invalid_authorization_header(): void
    {
        // Arrange
        $request = new Request();

        // Assert
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Missing or invalid JWT token');

        // Act
        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function authenticate_returns_self_validating_passport_for_valid_active_token(): void
    {
        // Arrange
        $token = $this->createToken(
            expiresAt: new \DateTimeImmutable('+1 hour'),
            subject: self::ACCOUNT_ID,
            email: 'user@example.com',
            role: 'ROLE_USER',
            active: true,
        );
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.$token);

        // Act
        $passport = $this->authenticator->authenticate($request);
        $badge = $passport->getBadge(UserBadge::class);
        $this->assertInstanceOf(UserBadge::class, $badge);
        $user = $badge->getUser();

        // Assert
        Assert::assertInstanceOf(SelfValidatingPassport::class, $passport);
        Assert::assertSame('user@example.com', $badge->getUserIdentifier());
        Assert::assertInstanceOf(AccountUser::class, $user);
        Assert::assertSame('user@example.com', $user->getUserIdentifier());
        Assert::assertSame(['ROLE_USER'], $user->getRoles());
        Assert::assertSame(self::ACCOUNT_ID, $user->getUserId()->asString());
        Assert::assertNull($user->getPassword());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function authenticate_rejects_token_with_invalid_signature(): void
    {
        // Arrange
        $otherKeyPair = \openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        \assert(false !== $otherKeyPair);
        \openssl_pkey_export($otherKeyPair, $otherPrivatePem);

        $token = $this->createToken(
            expiresAt: new \DateTimeImmutable('+1 hour'),
            subject: 'account-id',
            email: 'user@example.com',
            role: 'ROLE_USER',
            active: true,
            privateKeyBase64: \base64_encode((string) $otherPrivatePem),
        );
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.$token);

        // Assert
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid JWT token: Invalid token signature');

        // Act
        $this->authenticator->authenticate($request);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function authenticate_rejects_expired_token(): void
    {
        // Arrange
        $token = $this->createToken(
            expiresAt: new \DateTimeImmutable('-1 second'),
            subject: 'account-id',
            email: 'user@example.com',
            role: 'ROLE_USER',
            active: true,
        );
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.$token);

        // Assert
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid JWT token: Token has expired');

        // Act
        $this->authenticator->authenticate($request);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function authenticate_rejects_token_without_subject_claim(): void
    {
        // Arrange
        $token = $this->createToken(
            expiresAt: new \DateTimeImmutable('+1 hour'),
            subject: null,
            email: 'user@example.com',
            role: 'ROLE_USER',
            active: true,
        );
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.$token);

        // Assert
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid JWT token: Token missing subject claim');

        // Act
        $this->authenticator->authenticate($request);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function authenticate_rejects_inactive_account(): void
    {
        // Arrange
        $token = $this->createToken(
            expiresAt: new \DateTimeImmutable('+1 hour'),
            subject: 'account-id',
            email: 'user@example.com',
            role: 'ROLE_USER',
            active: false,
        );
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.$token);

        // Assert
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid JWT token: Account is not active');

        // Act
        $this->authenticator->authenticate($request);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function on_authentication_success_returns_null_response(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createStub(TokenInterface::class),
            'main',
        );

        // Assert
        Assert::assertNull($response);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function on_authentication_failure_returns_unauthorized_json_response(): void
    {
        // Arrange
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $exception = new CustomUserMessageAuthenticationException('Authentication message');

        // Act
        $response = $this->authenticator->onAuthenticationFailure($request, $exception);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
        $content = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Assert
        Assert::assertSame(401, $response->getStatusCode());
        Assert::assertSame([
            'error' => 'Authentication failed',
            'message' => 'Authentication message',
        ], $content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = \openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        \assert(false !== $keyPair);
        \openssl_pkey_export($keyPair, $privatePem);
        $publicPem = \openssl_pkey_get_details($keyPair)['key'];
        $this->testPrivateKeyBase64 = \base64_encode((string) $privatePem);
        $testPublicKeyBase64 = \base64_encode((string) $publicPem);

        $this->previousJwtPublicKey = $_ENV['JWT_PUBLIC_KEY'] ?? null;
        $_ENV['JWT_PUBLIC_KEY'] = $testPublicKeyBase64;
        $this->authenticator = new JwtAuthenticator();
    }

    protected function tearDown(): void
    {
        if (null === $this->previousJwtPublicKey) {
            unset($_ENV['JWT_PUBLIC_KEY']);
        } else {
            $_ENV['JWT_PUBLIC_KEY'] = $this->previousJwtPublicKey;
        }

        parent::tearDown();
    }

    private function createToken(
        \DateTimeImmutable $expiresAt,
        ?string $subject,
        ?string $email,
        ?string $role,
        ?bool $active,
        ?string $privateKeyBase64 = null,
    ): string {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($privateKeyBase64 ?? $this->testPrivateKeyBase64),
            InMemory::base64Encoded($privateKeyBase64 ?? $this->testPrivateKeyBase64),
        );

        /** @var Builder $builder */
        $builder = $configuration->builder()
            ->issuedBy('refleet-tests')
            ->issuedAt(new \DateTimeImmutable('-1 minute'))
            ->expiresAt($expiresAt);

        if (null !== $subject) {
            $builder = $builder->relatedTo($subject);
        }

        if (null !== $email) {
            $builder = $builder->withClaim('email', $email);
        }

        if (null !== $role) {
            $builder = $builder->withClaim('role', $role);
        }

        if (null !== $active) {
            $builder = $builder->withClaim('active', $active);
        }

        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }
}
