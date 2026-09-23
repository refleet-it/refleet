<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Application\Command\DeleteFile;

use App\File\File\Application\Command\DeleteFile\DeleteFileCommand;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteFileCommand::class)]
#[UsesClass(FileId::class)]
#[UsesClass(UserId::class)]
final class DeleteFileCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_all_fields(): void
    {
        // Arrange
        $fileId = FileId::fromString('11111111-2222-3333-4444-555555555555');
        $uploaderId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        // Act
        $command = new DeleteFileCommand(
            fileId: $fileId,
            uploaderId: $uploaderId,
        );

        // Assert
        Assert::assertSame($fileId, $command->fileId);
        Assert::assertSame($uploaderId, $command->uploaderId);
        Assert::assertSame('11111111-2222-3333-4444-555555555555', (string) $command->fileId);
        Assert::assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', (string) $command->uploaderId);
    }

    #[Test]
    public function accepts_generated_ids(): void
    {
        // Arrange
        $fileId = FileId::generate();
        $uploaderId = UserId::generate();

        // Act
        $command = new DeleteFileCommand(
            fileId: $fileId,
            uploaderId: $uploaderId,
        );

        // Assert
        Assert::assertInstanceOf(FileId::class, $command->fileId);
        Assert::assertInstanceOf(UserId::class, $command->uploaderId);
        Assert::assertNotSame('', $command->fileId->asString());
        Assert::assertNotSame('', $command->uploaderId->asString());
    }
}
