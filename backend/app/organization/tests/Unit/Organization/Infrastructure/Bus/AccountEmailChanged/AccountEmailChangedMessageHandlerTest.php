<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\Organization\Infrastructure\Bus\AccountEmailChanged;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Infrastructure\Bus\AccountEmailChanged\AccountEmailChangedMessage;
use App\Organization\Organization\Infrastructure\Bus\AccountEmailChanged\AccountEmailChangedMessageHandler;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountEmailChangedMessageHandler::class)]
final class AccountEmailChangedMessageHandlerTest extends TestCase
{
    private EmployeeRepositoryInterface&MockObject $repository;

    private AccountEmailChangedMessageHandler $handler;

    #[Test]
    public function updates_the_mirrored_email_when_it_changed(): void
    {
        // Arrange
        $accountId = AccountId::generate();
        $employee = Employee::mirror($accountId, 'old@example.com');
        $this->repository->method('findByAccountId')->willReturn($employee);
        $this->repository->expects($this->once())->method('save')->with($employee);

        // Act
        ($this->handler)(new AccountEmailChangedMessage(
            accountId: $accountId->asString(),
            email: 'new@example.com',
        ));

        // Assert
        Assert::assertSame('new@example.com', $employee->email());
    }

    #[Test]
    public function does_not_save_when_the_email_did_not_change(): void
    {
        // Arrange
        $accountId = AccountId::generate();
        $this->repository->method('findByAccountId')->willReturn(Employee::mirror($accountId, 'same@example.com'));
        $this->repository->expects($this->never())->method('save');

        // Act
        ($this->handler)(new AccountEmailChangedMessage(
            accountId: $accountId->asString(),
            email: 'same@example.com',
        ));
    }

    #[Test]
    public function does_nothing_when_no_mirror_exists_yet(): void
    {
        // Arrange
        $this->repository->method('findByAccountId')->willReturn(null);
        $this->repository->expects($this->never())->method('save');

        // Act
        ($this->handler)(new AccountEmailChangedMessage(
            accountId: AccountId::generate()->asString(),
            email: 'whatever@example.com',
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmployeeRepositoryInterface::class);
        $this->handler = new AccountEmailChangedMessageHandler($this->repository);
    }
}
