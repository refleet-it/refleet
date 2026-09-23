<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Infrastructure\Persistence;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use App\Identity\RefreshToken\Infrastructure\Persistence\DoctrineRefreshTokenRepository;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineRefreshTokenRepository::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RefreshTokenId::class)]
#[UsesClass(Account::class)]
#[UsesClass(AccountId::class)]
#[UsesClass(Email::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(Id::class)]
final class DoctrineRefreshTokenRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var EntityRepository<RefreshToken>&MockObject
     */
    private EntityRepository&MockObject $doctrineRepository;

    private DoctrineRefreshTokenRepository $repository;

    #[Test]
    public function find_by_token_hashes_the_case_insensitive_plaintext_before_lookup(): void
    {
        $originalToken = 'ToKeN-VALUE';
        $expectedHash = \hash('sha256', 'token-value');
        $refreshToken = $this->createRefreshToken(token: $expectedHash);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(RefreshToken::class))
            ->willReturn($this->doctrineRepository);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->identicalTo(['token' => $expectedHash]))
            ->willReturn($refreshToken);

        $result = $this->repository->findByToken($originalToken);

        Assert::assertSame($refreshToken, $result);
    }

    #[Test]
    public function find_by_token_returns_null_when_not_found(): void
    {
        $originalToken = 'missing-token';
        $expectedHash = \hash('sha256', $originalToken);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(RefreshToken::class))
            ->willReturn($this->doctrineRepository);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->identicalTo(['token' => $expectedHash]))
            ->willReturn(null);

        $result = $this->repository->findByToken($originalToken);

        Assert::assertNull($result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->doctrineRepository = $this->createMock(EntityRepository::class);

        $this->repository = new DoctrineRefreshTokenRepository($this->entityManager);
    }

    private function createRefreshToken(string $token): RefreshToken
    {
        return RefreshToken::create(
            id: RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            account: $this->createAccount(),
            token: $token,
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );
    }

    private function createAccount(): Account
    {
        return Account::create(
            AccountId::fromString('123e4567-e89b-12d3-a456-426614174000'),
            Email::fromString('user@example.com'),
            HashedPassword::fromString('hashed-password'),
            RoleEnum::USER,
        );
    }
}
