<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Event;

use App\File\File\Domain\Event\FileCreated;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[CoversClass(FileCreated::class)]
#[UsesClass(FileId::class)]
#[UsesClass(UserId::class)]
#[UsesClass(TenantId::class)]
final class FileCreatedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_all_properties_when_tenant_is_present(): void
    {
        // Arrange
        $fileId = FileId::fromString('11111111-2222-3333-4444-555555555555');
        $uploaderId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $tenantId = TenantId::fromString('tenant-123');
        $fileName = 'invoice-2026-02.pdf';

        // Act
        $event = new FileCreated(
            fileId: $fileId,
            fileName: $fileName,
            uploaderId: $uploaderId,
            tenantId: $tenantId,
        );

        // Assert
        Assert::assertSame($fileId, $event->fileId());
        Assert::assertSame($fileName, $event->fileName());
        Assert::assertSame($uploaderId, $event->uploaderId());
        Assert::assertSame($tenantId, $event->tenantId());
        Assert::assertInstanceOf(DomainEventInterface::class, $event);
    }

    #[Test]
    public function keeps_nullable_tenant_id_as_null_when_not_provided(): void
    {
        // Arrange
        $fileId = FileId::fromString('99999999-8888-7777-6666-555555555555');
        $uploaderId = UserId::fromString('eeeeeeee-dddd-cccc-bbbb-aaaaaaaaaaaa');
        $fileName = 'report.csv';

        // Act
        $event = new FileCreated(
            fileId: $fileId,
            fileName: $fileName,
            uploaderId: $uploaderId,
            tenantId: null,
        );

        // Assert
        Assert::assertSame($fileId, $event->fileId());
        Assert::assertSame($fileName, $event->fileName());
        Assert::assertSame($uploaderId, $event->uploaderId());
        Assert::assertNull($event->tenantId());
    }

    #[Test]
    public function preserves_file_name_without_normalization(): void
    {
        // Arrange
        $fileId = FileId::fromString('12345678-1234-1234-1234-123456789012');
        $uploaderId = UserId::fromString('21098765-4321-4321-4321-210987654321');
        $tenantId = TenantId::fromString('tenant-x');
        $fileName = '  budget Q1 2026 .xlsx  ';

        // Act
        $event = new FileCreated(
            fileId: $fileId,
            fileName: $fileName,
            uploaderId: $uploaderId,
            tenantId: $tenantId,
        );

        // Assert
        Assert::assertSame('  budget Q1 2026 .xlsx  ', $event->fileName());
    }

    #[Test]
    public function does_not_have_as_message_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(FileCreated::class);

        // Act
        $attributes = $reflection->getAttributes(AsMessage::class);

        // Assert
        Assert::assertCount(0, $attributes);
    }
}
