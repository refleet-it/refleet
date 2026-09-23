<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\ApproveCliAuthorization;

use App\Identity\Account\Application\Command\ApproveCliAuthorization\ApproveCliAuthorizationCommand;
use App\Identity\Account\Application\Command\ApproveCliAuthorization\ApproveCliAuthorizationHandler;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\CliAuthorization\Enum\CliAuthorizationStatusEnum;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use App\Identity\Account\Domain\CliAuthorization\ValueObject\CliAuthorizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApproveCliAuthorizationHandler::class)]
final class ApproveCliAuthorizationHandlerTest extends TestCase
{
    private CliAuthorizationRepositoryInterface&MockObject $authorizations;

    private AccountRepositoryInterface&Stub $accounts;

    private ApproveCliAuthorizationHandler $handler;

    #[Test]
    public function it_binds_the_approving_account_to_the_authorization(): void
    {
        // Arrange
        $account = $this->account();
        $authorization = $this->pending();
        $this->authorizations->method('findByUserCode')->with('user-code')->willReturn($authorization);
        $this->accounts->method('findById')->willReturn($account);
        $this->authorizations->expects($this->once())->method('save')->with($authorization);

        // Act
        ($this->handler)(new ApproveCliAuthorizationCommand($account->id()->asString(), 'user-code'));

        // Assert
        Assert::assertSame(CliAuthorizationStatusEnum::APPROVED, $authorization->status());
        Assert::assertSame($account, $authorization->account());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_throws_for_an_unknown_code(): void
    {
        $this->authorizations->method('findByUserCode')->willReturn(null);

        $this->expectException(CliAuthorizationNotFoundException::class);

        ($this->handler)(new ApproveCliAuthorizationCommand(AccountId::generate()->asString(), 'nope'));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_throws_when_the_approving_account_is_gone(): void
    {
        $this->authorizations->method('findByUserCode')->willReturn($this->pending());
        $this->accounts->method('findById')->willReturn(null);

        $this->expectException(AccountNotFoundException::class);

        ($this->handler)(new ApproveCliAuthorizationCommand(AccountId::generate()->asString(), 'user-code'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->authorizations = $this->createMock(CliAuthorizationRepositoryInterface::class);
        $this->accounts = $this->createStub(AccountRepositoryInterface::class);

        $this->handler = new ApproveCliAuthorizationHandler($this->authorizations, $this->accounts);
    }

    private function pending(): CliAuthorization
    {
        return CliAuthorization::create(CliAuthorizationId::generate(), 'user-code', 'hash', 'my-laptop', new \DateTimeImmutable('+10 minutes'));
    }

    private function account(): Account
    {
        return Account::create(AccountId::generate(), Email::fromString('owner@example.com'), HashedPassword::fromString('stored-hash'), RoleEnum::USER);
    }
}
