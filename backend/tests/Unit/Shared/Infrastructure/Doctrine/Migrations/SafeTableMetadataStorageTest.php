<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine\Migrations;

use App\Shared\Infrastructure\Doctrine\Migrations\SafeTableMetadataStorage;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafeTableMetadataStorage::class)]
final class SafeTableMetadataStorageTest extends TestCase
{
    #[Test]
    public function ensure_initialized_swallows_error_when_message_contains_already_exists(): void
    {
        // Arrange
        $decorated = $this->createMock(MetadataStorage::class);
        $decorated->expects($this->once())
            ->method('ensureInitialized')
            ->willThrowException(new \RuntimeException('relation "doctrine_migration_versions" already exists'));

        $subject = $this->createSubjectWithDecorated($decorated);

        // Act
        $subject->ensureInitialized();

        // Assert
        Assert::assertInstanceOf(SafeTableMetadataStorage::class, $subject);
    }

    #[Test]
    public function ensure_initialized_swallows_error_when_message_contains_sqlstate_42p07(): void
    {
        // Arrange
        $decorated = $this->createMock(MetadataStorage::class);
        $decorated->expects($this->once())
            ->method('ensureInitialized')
            ->willThrowException(new \RuntimeException('SQLSTATE[42P07]: Duplicate table: 7 ERROR: relation exists'));

        $subject = $this->createSubjectWithDecorated($decorated);

        // Act
        $subject->ensureInitialized();

        // Assert
        Assert::assertInstanceOf(SafeTableMetadataStorage::class, $subject);
    }

    #[Test]
    public function ensure_initialized_swallows_error_when_message_contains_duplicate_table(): void
    {
        // Arrange
        $decorated = $this->createMock(MetadataStorage::class);
        $decorated->expects($this->once())
            ->method('ensureInitialized')
            ->willThrowException(new \RuntimeException('Duplicate table: doctrine_migration_versions'));

        $subject = $this->createSubjectWithDecorated($decorated);

        // Act
        $subject->ensureInitialized();

        // Assert
        Assert::assertInstanceOf(SafeTableMetadataStorage::class, $subject);
    }

    #[Test]
    public function ensure_initialized_rethrows_error_for_non_duplicate_table_failures(): void
    {
        // Arrange
        $exception = new \RuntimeException('permission denied for table doctrine_migration_versions');
        $decorated = $this->createMock(MetadataStorage::class);
        $decorated->expects($this->once())
            ->method('ensureInitialized')
            ->willThrowException($exception);

        $subject = $this->createSubjectWithDecorated($decorated);

        // Act
        try {
            $subject->ensureInitialized();
            Assert::fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertSame($exception, $runtimeException);
            Assert::assertSame('permission denied for table doctrine_migration_versions', $runtimeException->getMessage());
        }
    }

    private function createSubjectWithDecorated(MetadataStorage $decorated): SafeTableMetadataStorage
    {
        $reflection = new \ReflectionClass(SafeTableMetadataStorage::class);
        $subject = $reflection->newInstanceWithoutConstructor();

        $property = $reflection->getProperty('decorated');
        $property->setValue($subject, $decorated);

        return $subject;
    }
}
