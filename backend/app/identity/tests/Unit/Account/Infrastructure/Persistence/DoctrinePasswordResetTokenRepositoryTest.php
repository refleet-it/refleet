<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Persistence;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Model\PasswordResetToken;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Persistence\DoctrinePasswordResetTokenRepository;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrinePasswordResetTokenRepository::class)]
#[UsesClass(PasswordResetToken::class)]
#[UsesClass(Account::class)]
#[UsesClass(AccountId::class)]
#[UsesClass(Email::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(Id::class)]
final class DoctrinePasswordResetTokenRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var EntityRepository<PasswordResetToken>&MockObject
     */
    private EntityRepository&MockObject $doctrineRepository;

    private DoctrinePasswordResetTokenRepository $repository;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function save_persists_token(): void
    {
        $token = $this->createToken();

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($token));

        $this->entityManager
            ->expects($this->never())
            ->method('getRepository');

        $this->repository->save($token);
    }

    #[Test]
    public function find_by_token_returns_matching_token(): void
    {
        $tokenValue = 'reset-token-value';
        $token = $this->createToken(token: $tokenValue);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(PasswordResetToken::class))
            ->willReturn($this->doctrineRepository);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['token' => $tokenValue])
            ->willReturn($token);

        $result = $this->repository->findByToken($tokenValue);

        Assert::assertSame($token, $result);
    }

    #[Test]
    public function find_by_token_returns_null_when_not_found(): void
    {
        $tokenValue = 'missing-token';

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(PasswordResetToken::class))
            ->willReturn($this->doctrineRepository);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['token' => $tokenValue])
            ->willReturn(null);

        $result = $this->repository->findByToken($tokenValue);

        Assert::assertNull($result);
    }

    #[Test]
    public function find_by_account_id_returns_matching_token(): void
    {
        $account = $this->createAccount();
        $token = $this->createToken(account: $account);
        $accountId = $account->id()->asString();

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(PasswordResetToken::class))
            ->willReturn($this->doctrineRepository);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['account' => $accountId])
            ->willReturn($token);

        $result = $this->repository->findByAccountId($accountId);

        Assert::assertSame($token, $result);
    }

    #[Test]
    public function find_by_account_id_returns_null_when_not_found(): void
    {
        $accountId = '11111111-2222-3333-4444-555555555555';

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(PasswordResetToken::class))
            ->willReturn($this->doctrineRepository);

        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['account' => $accountId])
            ->willReturn(null);

        $result = $this->repository->findByAccountId($accountId);

        Assert::assertNull($result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function delete_removes_token(): void
    {
        $token = $this->createToken();

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($this->identicalTo($token));

        $this->entityManager
            ->expects($this->never())
            ->method('getRepository');

        $this->repository->delete($token);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->doctrineRepository = $this->createMock(EntityRepository::class);

        $this->repository = new DoctrinePasswordResetTokenRepository($this->entityManager);
    }

    private function createToken(
        ?string $token = null,
        ?Account $account = null,
        ?string $id = null,
    ): PasswordResetToken {
        $account ??= $this->createAccount();

        return PasswordResetToken::create(
            id: Id::fromString($id ?? '550e8400-e29b-41d4-a716-446655440000'),
            account: $account,
            token: $token ?? 'default-reset-token',
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
