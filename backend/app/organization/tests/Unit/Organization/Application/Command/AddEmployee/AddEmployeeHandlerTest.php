<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Command\AddEmployee;

use App\Organization\Organization\Application\Command\AddEmployee\AddEmployeeCommand;
use App\Organization\Organization\Application\Command\AddEmployee\AddEmployeeHandler;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\Exception\EmployeeNotFoundException;
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

#[CoversClass(AddEmployeeHandler::class)]
final class AddEmployeeHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&MockObject $employees;

    private AddEmployeeHandler $handler;

    #[Test]
    public function owner_adds_a_registered_account_as_a_user(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization($organizationId, RoleEnum::OWNER);

        $target = Employee::mirror(AccountId::generate(), 'new-employee@example.com');

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->employees->method('findByEmail')->with('new-employee@example.com')->willReturn($target);
        $this->employees->expects($this->once())->method('save')->with($target);

        // Act
        $result = ($this->handler)(new AddEmployeeCommand($owner->accountId()->asString(), 'new-employee@example.com'));

        // Assert
        Assert::assertSame('new-employee@example.com', $result->email);
        Assert::assertSame('user', $result->role);
        Assert::assertTrue($target->belongsTo($organizationId));
        Assert::assertSame(RoleEnum::USER, $target->role());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_requester_has_no_employee_mirror(): void
    {
        // Arrange
        $this->employees->method('findByAccountId')->willReturn(null);

        // Assert
        $this->expectException(NotOrganizationOwnerException::class);

        // Act
        ($this->handler)(new AddEmployeeCommand(AccountId::generate()->asString(), 'new-employee@example.com'));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_requester_has_no_organization(): void
    {
        // Arrange
        $requester = Employee::mirror(AccountId::generate(), 'lonely@example.com');
        $this->employees->method('findByAccountId')->willReturn($requester);

        // Assert
        $this->expectException(NotOrganizationOwnerException::class);

        // Act
        ($this->handler)(new AddEmployeeCommand($requester->accountId()->asString(), 'new-employee@example.com'));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_requester_is_not_the_owner(): void
    {
        // Arrange
        $member = Employee::mirror(AccountId::generate(), 'member@example.com');
        $member->joinOrganization(OrganizationId::generate(), RoleEnum::USER);
        $this->employees->method('findByAccountId')->willReturn($member);

        // Assert
        $this->expectException(NotOrganizationOwnerException::class);

        // Act
        ($this->handler)(new AddEmployeeCommand($member->accountId()->asString(), 'new-employee@example.com'));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_no_registered_account_matches_the_email(): void
    {
        // Arrange
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);
        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->employees->method('findByEmail')->willReturn(null);

        // Assert
        $this->expectException(EmployeeNotFoundException::class);

        // Act
        ($this->handler)(new AddEmployeeCommand($owner->accountId()->asString(), 'unknown@example.com'));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_target_already_belongs_to_an_organization(): void
    {
        // Arrange
        $owner = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $owner->joinOrganization(OrganizationId::generate(), RoleEnum::OWNER);

        $target = Employee::mirror(AccountId::generate(), 'busy@example.com');
        $target->joinOrganization(OrganizationId::generate(), RoleEnum::USER);

        $this->employees->method('findByAccountId')->willReturn($owner);
        $this->employees->method('findByEmail')->willReturn($target);

        // Assert
        $this->expectException(AccountAlreadyBelongsToOrganizationException::class);

        // Act
        ($this->handler)(new AddEmployeeCommand($owner->accountId()->asString(), 'busy@example.com'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createMock(EmployeeRepositoryInterface::class);

        $this->handler = new AddEmployeeHandler($this->employees);
    }
}
