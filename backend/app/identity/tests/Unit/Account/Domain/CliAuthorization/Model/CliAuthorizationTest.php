<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\CliAuthorization\Model;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\CliAuthorization\Enum\CliAuthorizationStatusEnum;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationExpiredException;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotPendingException;
use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\ValueObject\CliAuthorizationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CliAuthorization::class)]
final class CliAuthorizationTest extends TestCase
{
    #[Test]
    public function it_starts_pending_without_an_account(): void
    {
        $authorization = $this->pending();

        Assert::assertSame(CliAuthorizationStatusEnum::PENDING, $authorization->status());
        Assert::assertTrue($authorization->isPending());
        Assert::assertFalse($authorization->isExpired());
        Assert::assertNull($authorization->account());
        Assert::assertNull($authorization->decidedAt());
        Assert::assertSame('user-code', $authorization->userCode());
        Assert::assertSame('secret-hash', $authorization->deviceSecretHash());
        Assert::assertSame('my-laptop', $authorization->runnerName());
    }

    #[Test]
    public function approving_records_the_account_and_the_moment(): void
    {
        $authorization = $this->pending();
        $account = $this->account();

        $authorization->approve($account);

        Assert::assertSame(CliAuthorizationStatusEnum::APPROVED, $authorization->status());
        Assert::assertSame($account, $authorization->account());
        Assert::assertNotNull($authorization->decidedAt());
    }

    #[Test]
    public function denying_leaves_no_account_behind(): void
    {
        $authorization = $this->pending();

        $authorization->deny();

        Assert::assertSame(CliAuthorizationStatusEnum::DENIED, $authorization->status());
        Assert::assertNull($authorization->account());
        Assert::assertNotNull($authorization->decidedAt());
    }

    #[Test]
    public function a_decided_authorization_cannot_be_decided_again(): void
    {
        $authorization = $this->pending();
        $authorization->deny();

        $this->expectException(CliAuthorizationNotPendingException::class);

        $authorization->approve($this->account());
    }

    #[Test]
    public function an_expired_authorization_cannot_be_approved(): void
    {
        $authorization = CliAuthorization::create(
            CliAuthorizationId::generate(),
            'user-code',
            'secret-hash',
            'my-laptop',
            new \DateTimeImmutable('-1 minute'),
        );

        Assert::assertTrue($authorization->isExpired());
        $this->expectException(CliAuthorizationExpiredException::class);

        $authorization->approve($this->account());
    }

    private function pending(): CliAuthorization
    {
        return CliAuthorization::create(
            CliAuthorizationId::generate(),
            'user-code',
            'secret-hash',
            'my-laptop',
            new \DateTimeImmutable('+10 minutes'),
        );
    }

    private function account(): Account
    {
        return Account::create(
            AccountId::generate(),
            Email::fromString('owner@example.com'),
            HashedPassword::fromString('stored-hash'),
            RoleEnum::USER,
        );
    }
}
