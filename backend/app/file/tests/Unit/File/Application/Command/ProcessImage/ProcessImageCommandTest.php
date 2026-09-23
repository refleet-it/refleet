<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Application\Command\ProcessImage;

use App\File\File\Application\Command\ProcessImage\ProcessImageCommand;
use App\File\File\Domain\ValueObject\FileId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessImageCommand::class)]
#[UsesClass(FileId::class)]
final class ProcessImageCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_all_fields(): void
    {
        // Arrange
        $fileId = FileId::fromString('11111111-2222-3333-4444-555555555555');
        $originalPath = '/uploads/images/source/photo.jpg';
        $mimeType = 'image/jpeg';

        // Act
        $command = new ProcessImageCommand(
            fileId: $fileId,
            originalPath: $originalPath,
            mimeType: $mimeType,
        );

        // Assert
        Assert::assertSame($fileId, $command->fileId);
        Assert::assertSame($originalPath, $command->originalPath);
        Assert::assertSame($mimeType, $command->mimeType);
        Assert::assertSame('11111111-2222-3333-4444-555555555555', $command->fileId->asString());
    }

    #[Test]
    public function keeps_empty_string_fields_as_provided(): void
    {
        // Arrange
        $fileId = FileId::generate();

        // Act
        $command = new ProcessImageCommand(
            fileId: $fileId,
            originalPath: '',
            mimeType: '',
        );

        // Assert
        Assert::assertSame($fileId, $command->fileId);
        Assert::assertSame('', $command->originalPath);
        Assert::assertSame('', $command->mimeType);
    }

    #[Test]
    public function rejects_non_string_original_path_or_mime_type(): void
    {
        // Arrange
        $fileId = FileId::generate();
        $originalPath = 123;

        // Act + Assert
        $this->expectException(\TypeError::class);
        new ProcessImageCommand(
            fileId: $fileId,
            originalPath: $originalPath,
            mimeType: 'image/png',
        );
    }

    #[Test]
    public function rejects_non_string_mime_type(): void
    {
        // Arrange
        $fileId = FileId::generate();
        $mimeType = false;

        // Act + Assert
        $this->expectException(\TypeError::class);
        new ProcessImageCommand(
            fileId: $fileId,
            originalPath: '/uploads/images/source/photo.png',
            mimeType: $mimeType,
        );
    }

    #[Test]
    public function does_not_allow_property_mutation(): void
    {
        // Arrange
        $command = new ProcessImageCommand(
            fileId: FileId::generate(),
            originalPath: '/uploads/images/source/photo.png',
            mimeType: 'image/png',
        );

        // Act
        $exception = null;
        try {
            $command->mimeType = 'image/webp';
        } catch (\Error $error) {
            $exception = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $exception);
    }
}
