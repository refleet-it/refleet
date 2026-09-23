<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Persistence;

use App\Fixtures\Factory\Identity\EmailVerificationTokenFactory;
use App\Identity\Account\Domain\Account\Model\EmailVerificationToken;
use App\Identity\Account\Infrastructure\Persistence\DoctrineEmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(DoctrineEmailVerificationTokenRepository::class)]
final class DoctrineEmailVerificationTokenRepositoryTest extends TestCase
{
    use Factories;

    private EntityManagerInterface&MockObject $entityManager;

    private EntityRepository&MockObject $doctrineRepository;

    private DoctrineEmailVerificationTokenRepository $repository;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function save_persists_token_without_flushing_or_querying(): void
    {
        // Arrange
        $token = EmailVerificationTokenFactory::new()->withoutPersisting()->create();
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($token));
        $this->entityManager
            ->expects($this->never())
            ->method('flush');
        $this->entityManager
            ->expects($this->never())
            ->method('getRepository');

        // Act
        $this->repository->save($token);

        // Assert
        Assert::assertInstanceOf(DoctrineEmailVerificationTokenRepository::class, $this->repository);
    }

    #[Test]
    public function find_by_token_returns_matching_token_from_doctrine_repository(): void
    {
        // Arrange
        $tokenValue = 'email-verification-token-value';
        $token = EmailVerificationTokenFactory::new()->withoutPersisting()->create(['token' => $tokenValue]);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(EmailVerificationToken::class))
            ->willReturn($this->doctrineRepository);
        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->identicalTo(['token' => $tokenValue]))
            ->willReturn($token);

        // Act
        $result = $this->repository->findByToken($tokenValue);

        // Assert
        Assert::assertSame($token, $result);
    }

    #[Test]
    public function find_by_token_returns_null_when_token_does_not_exist(): void
    {
        // Arrange
        $tokenValue = 'missing-email-verification-token';
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(EmailVerificationToken::class))
            ->willReturn($this->doctrineRepository);
        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->identicalTo(['token' => $tokenValue]))
            ->willReturn(null);

        // Act
        $result = $this->repository->findByToken($tokenValue);

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function find_by_token_uses_exact_input_without_normalization(): void
    {
        // Arrange
        $tokenValue = '  MixedCase-Token  ';
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->identicalTo(EmailVerificationToken::class))
            ->willReturn($this->doctrineRepository);
        $this->doctrineRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->identicalTo(['token' => $tokenValue]))
            ->willReturn(null);

        // Act
        $result = $this->repository->findByToken($tokenValue);

        // Assert
        Assert::assertNull($result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->doctrineRepository = $this->createMock(EntityRepository::class);

        $this->repository = new DoctrineEmailVerificationTokenRepository($this->entityManager);
    }
}
