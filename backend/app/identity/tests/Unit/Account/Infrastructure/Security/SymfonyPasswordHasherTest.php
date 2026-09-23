<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Infrastructure\Security\SymfonyPasswordHasher;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class SymfonyPasswordHasherTest extends TestCase
{
    private const string PLAIN_PASSWORD = 'plain-password';

    private const string HASHED_PASSWORD = 'hashed-password';

    private SymfonyPasswordHasher $passwordHasher;

    /** @var UserPasswordHasherInterface&MockObject */
    private UserPasswordHasherInterface $symfonyHasher;

    #[Test]
    public function hash_delegates_to_symfony_hasher_with_minimal_user(): void
    {
        $this->symfonyHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with(
                self::callback(static function (object $user): bool {
                    Assert::assertInstanceOf(PasswordAuthenticatedUserInterface::class, $user);
                    Assert::assertSame(['ROLE_USER'], $user->getRoles());
                    Assert::assertSame('temp', $user->getUserIdentifier());
                    Assert::assertSame('', $user->getPassword());

                    return true;
                }),
                self::PLAIN_PASSWORD,
            )
            ->willReturn(self::HASHED_PASSWORD);

        $hashed = $this->passwordHasher->hash(self::PLAIN_PASSWORD);

        Assert::assertSame(self::HASHED_PASSWORD, $hashed);
    }

    #[Test]
    public function verify_delegates_to_symfony_hasher_with_expected_user_state(): void
    {
        $this->symfonyHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with(
                self::callback(static function (object $user): bool {
                    Assert::assertInstanceOf(PasswordAuthenticatedUserInterface::class, $user);
                    Assert::assertSame(['ROLE_USER'], $user->getRoles());
                    Assert::assertSame('temp', $user->getUserIdentifier());
                    Assert::assertSame(self::HASHED_PASSWORD, $user->getPassword());

                    return true;
                }),
                self::PLAIN_PASSWORD,
            )
            ->willReturn(true);

        $isValid = $this->passwordHasher->verify(self::PLAIN_PASSWORD, self::HASHED_PASSWORD);

        Assert::assertTrue($isValid);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->symfonyHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordHasher = new SymfonyPasswordHasher($this->symfonyHasher);
    }
}
