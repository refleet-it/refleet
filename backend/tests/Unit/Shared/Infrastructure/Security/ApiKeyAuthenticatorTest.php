<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Domain\Service\ApiKeyValidatorInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\ValueObject\ValidatedApiKey;
use App\Shared\Infrastructure\Security\ApiKeyAuthenticator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[CoversClass(ApiKeyAuthenticator::class)]
final class ApiKeyAuthenticatorTest extends TestCase
{
    private const string ACCOUNT_ID = '11111111-1111-4111-8111-111111111111';

    private ApiKeyValidatorInterface&MockObject $validator;

    private ApiKeyAuthenticator $authenticator;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_supports_only_bearer_requests_with_the_api_key_prefix(): void
    {
        Assert::assertTrue($this->authenticator->supports($this->requestWithAuthHeader('Bearer ib_abc123')));
        Assert::assertFalse($this->authenticator->supports($this->requestWithAuthHeader('Bearer some.jwt.token')));
        Assert::assertFalse($this->authenticator->supports($this->requestWithAuthHeader(null)));
    }

    #[Test]
    public function it_authenticates_a_valid_key_and_resolves_to_the_owning_account(): void
    {
        // Arrange
        $validated = new ValidatedApiKey(
            apiKeyId: 'api-key-id',
            accountId: self::ACCOUNT_ID,
            email: 'owner@example.com',
            symfonyRoles: ['ROLE_USER'],
        );
        $this->validator->method('validate')->with('ib_the-plain-token')->willReturn($validated);

        // Act
        $passport = $this->authenticator->authenticate($this->requestWithAuthHeader('Bearer ib_the-plain-token'));
        $user = $passport->getUser();

        // Assert
        Assert::assertInstanceOf(AccountUser::class, $user);
        Assert::assertSame(self::ACCOUNT_ID, $user->getUserId()->asString());
        Assert::assertSame('owner@example.com', $user->getUserIdentifier());
        Assert::assertSame(['ROLE_USER'], $user->getRoles());
        Assert::assertSame('api-key-id', $user->getApiKeyId());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_rejects_when_the_validator_finds_no_usable_key(): void
    {
        // Arrange
        $this->validator->method('validate')->willReturn(null);

        // Assert
        $this->expectException(AuthenticationException::class);

        // Act
        $this->authenticator->authenticate($this->requestWithAuthHeader('Bearer ib_unknown-token'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = $this->createMock(ApiKeyValidatorInterface::class);
        $this->authenticator = new ApiKeyAuthenticator($this->validator);
    }

    private function requestWithAuthHeader(?string $value): Request
    {
        $request = new Request();
        if (null !== $value) {
            $request->headers->set('Authorization', $value);
        }

        return $request;
    }
}
