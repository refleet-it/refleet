<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Infrastructure\Bus\AccountMirrored;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Infrastructure\Bus\AccountMirrored\AccountMirroredMessage;
use App\Organization\Organization\Infrastructure\Bus\AccountMirrored\AccountMirroredMessageHandler;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountMirroredMessageHandler::class)]
final class AccountMirroredMessageHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&MockObject $repository;

    private AccountMirroredMessageHandler $handler;

    #[Test]
    public function mirrors_a_newly_created_account(): void
    {
        // Arrange
        $accountId = AccountId::generate();

        $this->repository->method('findByAccountId')->willReturn(null);

        $savedEmployee = null;
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Employee $employee) use (&$savedEmployee): bool {
                $savedEmployee = $employee;

                return true;
            }));

        // Act
        ($this->handler)(new AccountMirroredMessage(
            accountId: $accountId->asString(),
            email: 'Mixed.Case@example.com',
        ));

        // Assert
        Assert::assertNotNull($savedEmployee);
        Assert::assertSame($accountId->asString(), $savedEmployee->accountId()->asString());
        Assert::assertSame('mixed.case@example.com', $savedEmployee->email());
    }

    #[Test]
    public function does_not_mirror_twice_for_the_same_account(): void
    {
        // Arrange
        $accountId = AccountId::generate();
        $this->repository->method('findByAccountId')->willReturn(Employee::mirror($accountId, 'already@example.com'));

        $this->repository->expects($this->never())->method('save');

        // Act
        ($this->handler)(new AccountMirroredMessage(
            accountId: $accountId->asString(),
            email: 'already@example.com',
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmployeeRepositoryInterface::class);
        $this->handler = new AccountMirroredMessageHandler($this->repository);
    }
}
