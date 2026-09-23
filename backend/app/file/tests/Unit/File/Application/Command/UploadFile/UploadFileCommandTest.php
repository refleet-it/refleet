<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Application\Command\UploadFile;

use App\File\File\Application\Command\UploadFile\UploadFileCommand;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(UploadFileCommand::class)]
#[UsesClass(FileId::class)]
#[UsesClass(FileName::class)]
#[UsesClass(MimeType::class)]
#[UsesClass(FileSize::class)]
#[UsesClass(UserId::class)]
#[UsesClass(TenantId::class)]
final class UploadFileCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_all_fields(): void
    {
        // Arrange
        $fileId = FileId::fromString('11111111-2222-3333-4444-555555555555');
        $uploadedFile = $this->createUploadedFile('report.pdf', 'application/pdf', 'content');
        $fileName = FileName::fromString('stored-report.pdf');
        $originalName = FileName::fromString('report.pdf');
        $mimeType = MimeType::fromString('application/pdf');
        $size = FileSize::fromBytes(7);
        $description = 'Q1 report';
        $uploaderId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $tenantId = TenantId::fromString('tenant-1');

        // Act
        $command = new UploadFileCommand(
            fileId: $fileId,
            uploadedFile: $uploadedFile,
            fileName: $fileName,
            originalName: $originalName,
            mimeType: $mimeType,
            size: $size,
            description: $description,
            uploaderId: $uploaderId,
            tenantId: $tenantId,
        );

        // Assert
        Assert::assertSame($fileId, $command->fileId);
        Assert::assertSame($uploadedFile, $command->uploadedFile);
        Assert::assertSame($fileName, $command->fileName);
        Assert::assertSame($originalName, $command->originalName);
        Assert::assertSame($mimeType, $command->mimeType);
        Assert::assertSame($size, $command->size);
        Assert::assertSame($description, $command->description);
        Assert::assertSame($uploaderId, $command->uploaderId);
        Assert::assertSame($tenantId, $command->tenantId);
        Assert::assertSame('report.pdf', $command->uploadedFile->getClientOriginalName());
    }

    #[Test]
    public function keeps_optional_fields_null_when_not_provided(): void
    {
        // Arrange
        $uploadedFile = $this->createUploadedFile('photo.png', 'image/png', 'png-data');

        // Act
        $command = new UploadFileCommand(
            fileId: FileId::generate(),
            uploadedFile: $uploadedFile,
            fileName: FileName::fromString('photo.png'),
            originalName: FileName::fromString('photo.png'),
            mimeType: MimeType::fromString('image/png'),
            size: FileSize::fromBytes(8),
            description: null,
            uploaderId: UserId::generate(),
            tenantId: null,
        );

        // Assert
        Assert::assertNull($command->description);
        Assert::assertNull($command->tenantId);
    }

    #[Test]
    public function rejects_invalid_uploaded_file_type(): void
    {
        // Arrange
        $exception = null;

        // Act
        try {
            new UploadFileCommand(
                fileId: FileId::generate(),
                uploadedFile: 'not-an-uploaded-file',
                fileName: FileName::fromString('doc.pdf'),
                originalName: FileName::fromString('doc.pdf'),
                mimeType: MimeType::fromString('application/pdf'),
                size: FileSize::fromBytes(1),
                description: null,
                uploaderId: UserId::generate(),
                tenantId: null,
            );
        } catch (\TypeError $typeError) {
            $exception = $typeError;
        }

        // Assert
        Assert::assertInstanceOf(\TypeError::class, $exception);
    }

    #[Test]
    public function command_is_readonly_after_creation(): void
    {
        // Arrange
        $command = new UploadFileCommand(
            fileId: FileId::generate(),
            uploadedFile: $this->createUploadedFile('archive.zip', 'application/zip', 'zip'),
            fileName: FileName::fromString('archive.zip'),
            originalName: FileName::fromString('archive.zip'),
            mimeType: MimeType::fromString('application/zip'),
            size: FileSize::fromBytes(3),
            description: 'archive',
            uploaderId: UserId::generate(),
            tenantId: TenantId::fromString('tenant-2'),
        );
        $thrown = null;

        // Act
        try {
            $command->description = 'changed';
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $thrown);
        Assert::assertStringContainsString('Cannot modify readonly property', (string) ($thrown?->getMessage() ?? ''));
        Assert::assertStringContainsString('UploadFileCommand::$description', (string) ($thrown?->getMessage() ?? ''));
    }

    private function createUploadedFile(string $originalName, string $mimeType, string $content): UploadedFile
    {
        $path = (string) \tempnam(\sys_get_temp_dir(), 'upload_file_command_test_');
        \file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, $mimeType, null, true);
    }
}
