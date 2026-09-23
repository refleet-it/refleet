<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Command\RemoveEmployee;

use App\Organization\Organization\Application\Command\RemoveEmployee\RemoveEmployeeCommand;
use App\Organization\Organization\Application\Command\RemoveEmployee\RemoveEmployeeHandler;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\CannotRemoveSelfException;
use App\Organization\Organization\Domain\Employee\Exception\EmployeeNotInOrganizationException;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveEmployeeHandler::class)]
final class RemoveEmployeeHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&MockObject $employees;

    private RemoveEmployeeHandler $handler;

    #[Test]
    public function owner_removes_a_member_from_the_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $target = Employee::mirror(AccountId::generate(), 'member@example.com');
        $target->joinOrganization($organizationId, RoleEnum::USER);

        $this->employees->expects($this->exactly(2))->method('findByAccountId')->willReturnCallback(
            static fn (AccountId $accountId): ?Employee => match ($accountId->asString()) {
                $owner->accountId()->asString() => $owner,
                $target->accountId()->asString() => $target,
                default => null,
            }
        );
        $this->employees->expects($this->once())->method('save')->with($target);

        // Act
        ($this->handler)(new RemoveEmployeeCommand(
            $owner->accountId()->asString(),
            $target->accountId()->asString(),
        ));

        // Assert
        Assert::assertFalse($target->isInOrganization());
        Assert::assertNull($target->role());
        Assert::assertNull($target->joinedOrganizationAt());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function owner_cannot_remove_themselves(): void
    {
        // Arrange
        $ownerId = AccountId::generate()->asString();

        // Assert
        $this->expectException(CannotRemoveSelfException::class);

        // Act
        ($this->handler)(new RemoveEmployeeCommand($ownerId, $ownerId));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_requester_has_no_employee_mirror(): void
    {
        // Arrange
        $this->employees->method('findByAccountId')->willReturn(null);

        // Assert
        $this->expectException(NotOrganizationOwnerException::class);

        // Act
        ($this->handler)(new RemoveEmployeeCommand(AccountId::generate()->asString(), AccountId::generate()->asString()));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throws_when_requester_is_not_the_owner(): void
    {
        // Arrange
        $member = Employee::mirror(AccountId::generate(), 'member@example.com');
        $member->joinOrganization(OrganizationId::generate(), RoleEnum::USER);
        $this->employees->method('findByAccountId')->willReturn($member);

        // Assert
        $this->expectException(NotOrganizationOwnerException::class);

        // Act
        ($this->handler)(new RemoveEmployeeCommand($member->accountId()->asString(), AccountId::generate()->asString()));
    }

    #[Test]
    public function throws_when_target_is_not_in_the_organization(): void
    {
        // Arrange
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);

        $this->employees->expects($this->exactly(2))->method('findByAccountId')->willReturnCallback(
            static fn (AccountId $accountId): ?Employee => $accountId->asString() === $owner->accountId()->asString()
                ? $owner
                : null
        );

        // Assert
        $this->expectException(EmployeeNotInOrganizationException::class);

        // Act
        ($this->handler)(new RemoveEmployeeCommand($owner->accountId()->asString(), AccountId::generate()->asString()));
    }

    #[Test]
    public function throws_when_target_belongs_to_a_different_organization(): void
    {
        // Arrange
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);

        $target = Employee::mirror(AccountId::generate(), 'elsewhere@example.com');
        $target->joinOrganization(OrganizationId::generate(), RoleEnum::USER);

        $this->employees->expects($this->exactly(2))->method('findByAccountId')->willReturnCallback(
            static fn (AccountId $accountId): ?Employee => match ($accountId->asString()) {
                $owner->accountId()->asString() => $owner,
                $target->accountId()->asString() => $target,
                default => null,
            }
        );

        // Assert
        $this->expectException(EmployeeNotInOrganizationException::class);

        // Act
        ($this->handler)(new RemoveEmployeeCommand($owner->accountId()->asString(), $target->accountId()->asString()));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createMock(EmployeeRepositoryInterface::class);

        $this->handler = new RemoveEmployeeHandler($this->employees);
    }
}
