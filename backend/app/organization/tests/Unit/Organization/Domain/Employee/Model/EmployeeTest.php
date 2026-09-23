<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Domain\Employee\Model;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Employee::class)]
final class EmployeeTest extends TestCase
{
    #[Test]
    public function mirrors_account_without_organization(): void
    {
        $accountId = AccountId::generate();

        $employee = Employee::mirror($accountId, 'Test@Example.com');

        Assert::assertTrue($employee->accountId()->equals($accountId));
        Assert::assertSame('test@example.com', $employee->email());
        Assert::assertFalse($employee->isInOrganization());
        Assert::assertNull($employee->organizationId());
        Assert::assertNull($employee->role());
        Assert::assertNull($employee->joinedOrganizationAt());
    }

    #[Test]
    public function joins_organization_with_role(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $organizationId = OrganizationId::generate();

        $employee->joinOrganization($organizationId, RoleEnum::OWNER);

        Assert::assertTrue($employee->isInOrganization());
        Assert::assertTrue($employee->belongsTo($organizationId));
        Assert::assertSame(RoleEnum::OWNER, $employee->role());
        Assert::assertNotNull($employee->joinedOrganizationAt());
    }

    #[Test]
    public function cannot_join_a_second_organization(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);

        $this->expectException(AccountAlreadyBelongsToOrganizationException::class);

        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::USER);
    }

    #[Test]
    public function syncs_email_and_reports_when_changed(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'old@example.com');

        $changed = $employee->syncEmail('New@Example.com');

        Assert::assertTrue($changed);
        Assert::assertSame('new@example.com', $employee->email());
    }

    #[Test]
    public function does_not_report_change_when_email_is_the_same(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'same@example.com');

        $changed = $employee->syncEmail('SAME@Example.com');

        Assert::assertFalse($changed);
        Assert::assertSame('same@example.com', $employee->email());
    }

    #[Test]
    public function leaves_organization(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'member@example.com');
        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::USER);

        $employee->leaveOrganization();

        Assert::assertFalse($employee->isInOrganization());
        Assert::assertNull($employee->organizationId());
        Assert::assertNull($employee->role());
        Assert::assertNull($employee->joinedOrganizationAt());
    }

    #[Test]
    public function can_rejoin_a_different_organization_after_leaving(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'member@example.com');
        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::USER);
        $employee->leaveOrganization();

        $newOrganizationId = OrganizationId::generate();
        $employee->joinOrganization($newOrganizationId, RoleEnum::OWNER);

        Assert::assertTrue($employee->belongsTo($newOrganizationId));
        Assert::assertSame(RoleEnum::OWNER, $employee->role());
    }

    #[Test]
    public function changes_role(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'member@example.com');
        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::USER);

        $employee->changeRole(RoleEnum::OWNER);

        Assert::assertSame(RoleEnum::OWNER, $employee->role());
    }

    #[Test]
    public function does_not_belong_to_an_unrelated_organization(): void
    {
        $employee = Employee::mirror(AccountId::generate(), 'user@example.com');
        $employee->joinOrganization(OrganizationId::generate(), RoleEnum::USER);

        Assert::assertFalse($employee->belongsTo(OrganizationId::generate()));
    }
}
