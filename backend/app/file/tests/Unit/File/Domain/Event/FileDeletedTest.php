<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\Event;

use App\File\File\Domain\Event\FileDeleted;
use App\File\File\Domain\ValueObject\FileId;
use App\Shared\Domain\User\UserId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[CoversClass(FileDeleted::class)]
#[UsesClass(FileId::class)]
#[UsesClass(UserId::class)]
final class FileDeletedTest extends TestCase
{
    #[Test]
    public function creates_event_and_exposes_properties(): void
    {
        // Arrange
        $fileId = FileId::fromString('11111111-2222-3333-4444-555555555555');
        $uploaderId = UserId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        // Act
        $event = new FileDeleted(
            fileId: $fileId,
            uploaderId: $uploaderId,
        );

        // Assert
        Assert::assertSame($fileId, $event->fileId());
        Assert::assertSame($uploaderId, $event->uploaderId());
    }

    #[Test]
    public function has_as_message_sync_attribute(): void
    {
        // Arrange & Act
        $reflection = new \ReflectionClass(FileDeleted::class);
        $attributes = $reflection->getAttributes(AsMessage::class);

        // Assert
        Assert::assertNotEmpty($attributes);
        $attribute = $attributes[0]->newInstance();
        Assert::assertSame('sync', $attribute->transport);
    }
}
