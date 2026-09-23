<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Infrastructure\Security\LcobucciTokenGenerator;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(LcobucciTokenGenerator::class)]
final class LcobucciTokenGeneratorTest extends TestCase
{
    use Factories;

    private string $testPrivateKeyBase64;

    private string $testPublicKeyBase64;

    private bool $hadJwtPrivateKey;

    private bool $hadJwtPublicKey;

    private ?string $previousJwtPrivateKey;

    private ?string $previousJwtPublicKey;

    #[Test]
    public function constructor_throws_when_jwt_private_key_is_missing(): void
    {
        // Arrange
        unset($_ENV['JWT_PRIVATE_KEY']);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT_PRIVATE_KEY environment variable is not set');

        // Act
        new LcobucciTokenGenerator();
    }

    #[Test]
    public function constructor_throws_when_jwt_private_key_is_empty(): void
    {
        // Arrange
        $_ENV['JWT_PRIVATE_KEY'] = '';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT_PRIVATE_KEY must be a non-empty string');

        // Act
        new LcobucciTokenGenerator();
    }

    #[Test]
    public function constructor_throws_when_jwt_public_key_is_missing(): void
    {
        // Arrange
        $_ENV['JWT_PRIVATE_KEY'] = $this->testPrivateKeyBase64;
        unset($_ENV['JWT_PUBLIC_KEY']);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT_PUBLIC_KEY environment variable is not set');

        // Act
        new LcobucciTokenGenerator();
    }

    #[Test]
    public function constructor_throws_when_jwt_public_key_is_empty(): void
    {
        // Arrange
        $_ENV['JWT_PRIVATE_KEY'] = $this->testPrivateKeyBase64;
        $_ENV['JWT_PUBLIC_KEY'] = '';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT_PUBLIC_KEY must be a non-empty string');

        // Act
        new LcobucciTokenGenerator();
    }

    #[Test]
    public function generate_creates_token_with_required_claims(): void
    {
        // Arrange
        $_ENV['JWT_PRIVATE_KEY'] = $this->testPrivateKeyBase64;
        $_ENV['JWT_PUBLIC_KEY'] = $this->testPublicKeyBase64;
        $account = AccountFactory::new(['role' => RoleEnum::USER])->withoutPersisting()->create();
        $expectedAccountId = $account->id()->asString();
        $expectedRole = $account->role()->toSymfonyRole();

        $generator = new LcobucciTokenGenerator();

        // Act
        $tokenString = $generator->generate($account);
        $token = $this->parseToken($tokenString);

        // Assert
        Assert::assertSame('refleet', $token->claims()->get('iss'));
        Assert::assertSame($expectedAccountId, $token->claims()->get('sub'));
        Assert::assertSame($account->email(), $token->claims()->get('email'));
        Assert::assertSame($expectedRole, $token->claims()->get('role'));
        Assert::assertTrue($token->claims()->get('active'));
        Assert::assertFalse($token->claims()->has('impersonatorId'));
        Assert::assertSame(3600, $token->claims()->get('exp')->getTimestamp() - $token->claims()->get('iat')->getTimestamp());
    }

    #[Test]
    public function generate_adds_impersonator_id_when_provided(): void
    {
        // Arrange
        $_ENV['JWT_PRIVATE_KEY'] = $this->testPrivateKeyBase64;
        $_ENV['JWT_PUBLIC_KEY'] = $this->testPublicKeyBase64;
        $account = AccountFactory::new(['role' => RoleEnum::USER])->withoutPersisting()->create();
        $impersonatorId = AccountId::generate();

        $generator = new LcobucciTokenGenerator();

        // Act
        $tokenString = $generator->generate($account, $impersonatorId);
        $token = $this->parseToken($tokenString);

        // Assert
        Assert::assertSame($impersonatorId->asString(), $token->claims()->get('impersonatorId'));
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
        $this->testPublicKeyBase64 = \base64_encode((string) $publicPem);

        $this->hadJwtPrivateKey = \array_key_exists('JWT_PRIVATE_KEY', $_ENV);
        $this->previousJwtPrivateKey = $this->hadJwtPrivateKey ? $_ENV['JWT_PRIVATE_KEY'] : null;
        $this->hadJwtPublicKey = \array_key_exists('JWT_PUBLIC_KEY', $_ENV);
        $this->previousJwtPublicKey = $this->hadJwtPublicKey ? $_ENV['JWT_PUBLIC_KEY'] : null;
    }

    protected function tearDown(): void
    {
        if ($this->hadJwtPrivateKey) {
            $_ENV['JWT_PRIVATE_KEY'] = $this->previousJwtPrivateKey;
        } else {
            unset($_ENV['JWT_PRIVATE_KEY']);
        }

        if ($this->hadJwtPublicKey) {
            $_ENV['JWT_PUBLIC_KEY'] = $this->previousJwtPublicKey;
        } else {
            unset($_ENV['JWT_PUBLIC_KEY']);
        }

        parent::tearDown();
    }

    private function parseToken(string $tokenString): Plain
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($this->testPublicKeyBase64),
            InMemory::base64Encoded($this->testPublicKeyBase64),
        );

        return $configuration->parser()->parse($tokenString);
    }
}
