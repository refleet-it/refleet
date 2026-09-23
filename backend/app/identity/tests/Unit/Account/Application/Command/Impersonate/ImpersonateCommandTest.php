<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\Impersonate;

use App\Identity\Account\Application\Command\Impersonate\ImpersonateCommand;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImpersonateCommand::class)]
#[UsesClass(AccountId::class)]
final class ImpersonateCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_all_fields(): void
    {
        // Arrange
        $adminId = AccountId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $targetId = AccountId::fromString('11111111-2222-3333-4444-555555555555');

        // Act
        $command = new ImpersonateCommand(
            adminAccountId: $adminId,
            targetAccountId: $targetId,
        );

        // Assert
        Assert::assertSame($adminId, $command->adminAccountId);
        Assert::assertSame($targetId, $command->targetAccountId);
        Assert::assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', (string) $command->adminAccountId);
        Assert::assertSame('11111111-2222-3333-4444-555555555555', (string) $command->targetAccountId);
    }

    #[Test]
    public function accepts_generated_ids(): void
    {
        // Arrange
        $adminId = AccountId::generate();
        $targetId = AccountId::generate();

        // Act
        $command = new ImpersonateCommand(
            adminAccountId: $adminId,
            targetAccountId: $targetId,
        );

        // Assert
        Assert::assertInstanceOf(AccountId::class, $command->adminAccountId);
        Assert::assertInstanceOf(AccountId::class, $command->targetAccountId);
        Assert::assertNotSame('', $command->adminAccountId->asString());
        Assert::assertNotSame('', $command->targetAccountId->asString());
    }
}
