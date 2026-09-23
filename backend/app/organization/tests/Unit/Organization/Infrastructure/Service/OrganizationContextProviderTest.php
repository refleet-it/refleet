<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Infrastructure\Service;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Exception\OrganizationNotFoundException;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Organization\Organization\Infrastructure\Service\OrganizationContextProvider;
use App\Shared\Domain\Exception\AccountHasNoOrganizationException;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * This is the port every other context asks "which organization is this request for", so it decides
 * the tenancy boundary for callers that cannot see Organization's own model. The cases here hold
 * that it fails closed when membership is missing, and that the organization it resolves is the one
 * recorded on the employee rather than whatever the repository happens to return.
 */
#[CoversClass(OrganizationContextProvider::class)]
#[UsesClass(Employee::class)]
#[UsesClass(Organization::class)]
final class OrganizationContextProviderTest extends TestCase
{
    private const string ACCOUNT_ID = '11111111-2222-3333-4444-555555555555';

    // Returning some empty or default context instead would let the caller carry on believing the
    // request is scoped to an organization when it belongs to none.
    #[Test]
    public function refuses_an_account_with_no_employee_record(): void
    {
        // Arrange
        $provider = $this->provider(employee: null);

        // Assert
        $this->expectException(AccountHasNoOrganizationException::class);

        // Act
        $provider->requireForAccount(UserId::fromString(self::ACCOUNT_ID));
    }

    #[Test]
    public function refuses_an_employee_who_belongs_to_no_organization(): void
    {
        // Arrange
        $provider = $this->provider(employee: $this->employee());

        // Assert
        $this->expectException(AccountHasNoOrganizationException::class);

        // Act
        $provider->requireForAccount(UserId::fromString(self::ACCOUNT_ID));
    }

    #[Test]
    public function refuses_when_the_organization_on_the_employee_no_longer_exists(): void
    {
        // Arrange
        $provider = $this->provider(employee: $this->employeeOf($this->organization()), organization: null);

        // Assert
        $this->expectException(OrganizationNotFoundException::class);

        // Act
        $provider->requireForAccount(UserId::fromString(self::ACCOUNT_ID));
    }

    // The tenancy boundary itself: the employee record names the organization, and asking for any
    // other one hands this account a context belonging to somebody else.
    #[Test]
    public function looks_up_the_organization_recorded_on_the_employee(): void
    {
        // Arrange
        $organization = $this->organization();

        $employees = $this->createStub(EmployeeRepositoryInterface::class);
        $employees->method('findByAccountId')->willReturn($this->employeeOf($organization));

        $organizations = $this->createMock(OrganizationRepositoryInterface::class);
        $organizations
            ->expects($this->once())
            ->method('findById')
            ->with($organization->id())
            ->willReturn($organization);

        $provider = new OrganizationContextProvider($employees, $organizations);

        // Act
        $provider->requireForAccount(UserId::fromString(self::ACCOUNT_ID));
    }

    #[Test]
    public function describes_the_organization_and_the_role_the_employee_holds_in_it(): void
    {
        // Arrange
        $organization = $this->organization('Refleet');
        $provider = $this->provider(
            employee: $this->employeeOf($organization, RoleEnum::OWNER),
            organization: $organization,
        );

        // Act
        $context = $provider->requireForAccount(UserId::fromString(self::ACCOUNT_ID));

        // Assert
        Assert::assertSame($organization->id()->asString(), $context->id);
        Assert::assertSame('Refleet', $context->name);
        Assert::assertSame(RoleEnum::OWNER->value, $context->role);
    }

    private function provider(?Employee $employee, ?Organization $organization = null): OrganizationContextProvider
    {
        $employees = $this->createStub(EmployeeRepositoryInterface::class);
        $employees->method('findByAccountId')->willReturn($employee);

        $organizations = $this->createStub(OrganizationRepositoryInterface::class);
        $organizations->method('findById')->willReturn($organization);

        return new OrganizationContextProvider($employees, $organizations);
    }

    private function employee(): Employee
    {
        return Employee::mirror(AccountId::fromString(self::ACCOUNT_ID), 'employee@refleet.it');
    }

    private function employeeOf(Organization $organization, RoleEnum $role = RoleEnum::USER): Employee
    {
        $employee = $this->employee();
        $employee->joinOrganization($organization->id(), $role);

        return $employee;
    }

    private function organization(string $name = 'Acme'): Organization
    {
        return Organization::create(
            OrganizationId::generate(),
            $name,
            AccountId::fromString(self::ACCOUNT_ID),
        );
    }
}
