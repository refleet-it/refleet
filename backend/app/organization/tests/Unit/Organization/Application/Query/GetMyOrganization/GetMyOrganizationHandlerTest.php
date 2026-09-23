<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Query\GetMyOrganization;

use App\Organization\Organization\Application\Query\GetMyOrganization\GetMyOrganizationHandler;
use App\Organization\Organization\Application\Query\GetMyOrganization\GetMyOrganizationQuery;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Exception\OrganizationNotFoundException;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetMyOrganizationHandler::class)]
final class GetMyOrganizationHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&Stub $employees;

    private OrganizationRepositoryInterface&Stub $organizations;

    private GetMyOrganizationHandler $handler;

    #[Test]
    public function returns_null_when_account_has_no_employee_mirror(): void
    {
        // Arrange
        $this->employees->method('findByAccountId')->willReturn(null);

        // Act
        $result = ($this->handler)(new GetMyOrganizationQuery(AccountId::generate()->asString()));

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function returns_null_when_account_does_not_belong_to_an_organization(): void
    {
        // Arrange
        $employee = Employee::mirror(AccountId::generate(), 'lonely@example.com');
        $this->employees->method('findByAccountId')->willReturn($employee);

        // Act
        $result = ($this->handler)(new GetMyOrganizationQuery($employee->accountId()->asString()));

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function returns_overview_with_role(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $organization = Organization::create($organizationId, 'Acme Inc.', AccountId::generate());

        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->organizations->method('findById')->willReturn($organization);

        // Act
        $result = ($this->handler)(new GetMyOrganizationQuery($owner->accountId()->asString()));

        // Assert
        Assert::assertNotNull($result);
        Assert::assertSame('Acme Inc.', $result->name);
        Assert::assertSame('owner', $result->role);
    }

    #[Test]
    public function throws_when_organization_record_is_missing(): void
    {
        // Arrange
        $employee = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);
        $this->employees->method('findByAccountId')->willReturn($employee);
        $this->organizations->method('findById')->willReturn(null);

        // Assert
        $this->expectException(OrganizationNotFoundException::class);

        // Act
        ($this->handler)(new GetMyOrganizationQuery($employee->accountId()->asString()));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createStub(EmployeeRepositoryInterface::class);
        $this->organizations = $this->createStub(OrganizationRepositoryInterface::class);

        $this->handler = new GetMyOrganizationHandler($this->employees, $this->organizations);
    }
}
