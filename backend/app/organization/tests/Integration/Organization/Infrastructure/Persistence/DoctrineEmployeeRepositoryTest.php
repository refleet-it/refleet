<?php

declare(strict_types=1);

namespace App\Tests\Integration\Organization\Organization\Infrastructure\Persistence;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Organization\Organization\Infrastructure\Persistence\DoctrineEmployeeRepository;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineEmployeeRepository::class)]
final class DoctrineEmployeeRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineEmployeeRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_an_employee_by_account_id(): void
    {
        // Arrange
        $accountId = AccountId::generate();
        $employee = Employee::mirror($accountId, 'member@example.com');

        // Act
        $this->repository->save($employee);
        $this->entityManager->clear();
        $found = $this->repository->findByAccountId($accountId);

        // Assert
        Assert::assertInstanceOf(Employee::class, $found);
        Assert::assertSame('member@example.com', $found->email());
        Assert::assertFalse($found->isInOrganization());
    }

    #[Test]
    public function find_by_email_is_case_insensitive(): void
    {
        // Arrange
        $employee = Employee::mirror(AccountId::generate(), 'Member@Example.com');
        $this->repository->save($employee);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByEmail('MEMBER@example.com');

        // Assert
        Assert::assertInstanceOf(Employee::class, $found);
    }

    #[Test]
    public function finds_all_employees_of_an_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $this->repository->save($owner);

        $member = Employee::mirror(AccountId::generate(), 'member@example.com');
        $member->joinOrganization($organizationId, RoleEnum::USER);

        $this->repository->save($member);

        $outsider = Employee::mirror(AccountId::generate(), 'outsider@example.com');
        $outsider->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);

        $this->repository->save($outsider);

        $this->entityManager->clear();

        // Act
        $employees = $this->repository->findByOrganizationId($organizationId);

        // Assert
        Assert::assertCount(2, $employees);
    }

    #[Test]
    public function paginates_employees_of_an_organization_sorted_by_email(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        foreach (['charlie@example.com', 'alice@example.com', 'bob@example.com'] as $email) {
            $employee = Employee::mirror(AccountId::generate(), $email);
            $employee->joinOrganization($organizationId, RoleEnum::USER);
            $this->repository->save($employee);
        }

        $this->entityManager->clear();

        // Act
        $firstPage = $this->repository->getPaginatedList($organizationId, PaginationParameters::fromRequest(page: 1, limit: 2), null);
        $secondPage = $this->repository->getPaginatedList($organizationId, PaginationParameters::fromRequest(page: 2, limit: 2), null);

        // Assert
        Assert::assertCount(2, $firstPage->getItems());
        Assert::assertSame(3, $firstPage->getTotalItems());
        Assert::assertTrue($firstPage->hasNextPage());
        Assert::assertSame('alice@example.com', $firstPage->getItems()[0]->email());
        Assert::assertSame('bob@example.com', $firstPage->getItems()[1]->email());

        Assert::assertCount(1, $secondPage->getItems());
        Assert::assertFalse($secondPage->hasNextPage());
        Assert::assertSame('charlie@example.com', $secondPage->getItems()[0]->email());
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineEmployeeRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Employee::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }
}
