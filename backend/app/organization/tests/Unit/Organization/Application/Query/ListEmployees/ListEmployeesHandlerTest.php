<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Application\Query\ListEmployees;

use App\Organization\Organization\Application\Query\ListEmployees\ListEmployeesHandler;
use App\Organization\Organization\Application\Query\ListEmployees\ListEmployeesQuery;
use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListEmployeesHandler::class)]
final class ListEmployeesHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&Stub $employees;

    private ListEmployeesHandler $handler;

    #[Test]
    public function maps_the_organizations_employees_to_overviews(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $employee = Employee::mirror(AccountId::generate(), 'owner@example.com');
        $employee->joinOrganization($organizationId, RoleEnum::OWNER);

        $this->employees
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$employee],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));

        // Act
        $result = ($this->handler)(new ListEmployeesQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame($employee->accountId()->asString(), $result->getItems()[0]->accountId);
        Assert::assertSame('owner@example.com', $result->getItems()[0]->email);
        Assert::assertSame('owner', $result->getItems()[0]->role);
    }

    #[Test]
    public function returns_an_empty_page_when_the_organization_has_no_employees(): void
    {
        // Arrange
        $this->employees
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [],
                totalItems: 0,
                pagination: PaginationParameters::fromRequest(),
            ));

        // Act
        $result = ($this->handler)(new ListEmployeesQuery(organizationId: OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame([], $result->getItems());
        Assert::assertFalse($result->hasNextPage());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->employees = $this->createStub(EmployeeRepositoryInterface::class);
        $this->handler = new ListEmployeesHandler($this->employees);
    }
}
