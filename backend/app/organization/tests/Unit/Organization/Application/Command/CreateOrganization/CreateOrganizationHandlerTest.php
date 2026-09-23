<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Command\CreateOrganization;

use App\Organization\Organization\Application\Command\CreateOrganization\CreateOrganizationCommand;
use App\Organization\Organization\Application\Command\CreateOrganization\CreateOrganizationHandler;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateOrganizationHandler::class)]
final class CreateOrganizationHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&MockObject $employees;

    private OrganizationRepositoryInterface&MockObject $organizations;

    private CreateOrganizationHandler $handler;

    #[Test]
    public function creates_organization_and_makes_the_account_its_owner(): void
    {
        // Arrange
        $accountId = AccountId::generate();
        $employee = Employee::mirror($accountId, 'owner@example.com');
        $this->employees->method('findByAccountId')->willReturn($employee);

        $savedOrganization = null;
        $this->organizations
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Organization $organization) use (&$savedOrganization): bool {
                $savedOrganization = $organization;

                return true;
            }));

        $this->employees
            ->expects($this->once())
            ->method('save')
            ->with($employee);

        // Act
        $result = ($this->handler)(new CreateOrganizationCommand($accountId->asString(), 'owner@example.com', 'Acme Inc.'));

        // Assert
        Assert::assertNotNull($savedOrganization);
        Assert::assertSame('Acme Inc.', $result->name);
        Assert::assertSame('owner', $result->role);
        Assert::assertTrue($employee->isInOrganization());
        Assert::assertSame(RoleEnum::OWNER, $employee->role());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function backfills_the_employee_mirror_when_the_account_has_none_yet(): void
    {
        // Arrange
        $accountId = AccountId::generate();
        $this->employees->method('findByAccountId')->willReturn(null);

        $savedEmployee = null;
        $this->employees
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Employee $employee) use (&$savedEmployee): bool {
                $savedEmployee = $employee;

                return true;
            }));

        // Act
        $result = ($this->handler)(new CreateOrganizationCommand($accountId->asString(), 'owner@example.com', 'Acme Inc.'));

        // Assert
        Assert::assertNotNull($savedEmployee);
        Assert::assertTrue($savedEmployee->accountId()->equals($accountId));
        Assert::assertSame('owner@example.com', $savedEmployee->email());
        Assert::assertTrue($savedEmployee->isInOrganization());
        Assert::assertSame('owner', $result->role);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_account_already_belongs_to_an_organization(): void
    {
        // Arrange
        $employee = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);
        $this->employees->method('findByAccountId')->willReturn($employee);

        // Assert
        $this->expectException(AccountAlreadyBelongsToOrganizationException::class);

        // Act
        ($this->handler)(new CreateOrganizationCommand(AccountId::generate()->asString(), 'owner@example.com', 'Acme Inc.'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createMock(EmployeeRepositoryInterface::class);
        $this->organizations = $this->createMock(OrganizationRepositoryInterface::class);

        $this->handler = new CreateOrganizationHandler($this->employees, $this->organizations);
    }
}
