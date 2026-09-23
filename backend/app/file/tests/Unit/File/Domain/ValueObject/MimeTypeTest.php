<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Domain\ValueObject;

use App\File\File\Domain\Exception\InvalidMimeTypeException;
use App\File\File\Domain\Exception\MimeTypeCannotBeEmptyException;
use App\File\File\Domain\ValueObject\MimeType;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MimeTypeTest extends TestCase
{
    #[Test]
    public function should_create_mime_type_from_valid_string(): void
    {
        // Arrange
        $input = 'image/jpeg';
        $expected = 'image/jpeg';

        // Act
        $mimeType = MimeType::fromString($input);

        // Assert
        Assert::assertSame($expected, $mimeType->asString());
        Assert::assertSame($expected, (string) $mimeType);
    }

    #[Test]
    public function should_throw_exception_when_mime_type_is_empty(): void
    {
        // Arrange
        $empty = '';

        // Act & Assert
        $this->expectException(MimeTypeCannotBeEmptyException::class);
        MimeType::fromString($empty);
    }

    #[Test]
    public function should_throw_exception_when_mime_type_is_zero_string(): void
    {
        // Arrange
        $zero = '0';

        // Act & Assert
        $this->expectException(MimeTypeCannotBeEmptyException::class);
        MimeType::fromString($zero);
    }

    #[Test]
    public function should_throw_exception_when_mime_type_is_whitespace_only(): void
    {
        // Arrange
        $whitespace = '   ';

        // Act & Assert
        $this->expectException(MimeTypeCannotBeEmptyException::class);
        MimeType::fromString($whitespace);
    }

    #[Test]
    public function should_throw_exception_when_mime_type_is_not_allowed(): void
    {
        // Arrange
        $notAllowed = 'application/json';

        // Act & Assert
        $this->expectException(InvalidMimeTypeException::class);
        MimeType::fromString($notAllowed);
    }

    #[Test]
    public function should_identify_image_mime_type(): void
    {
        // Arrange
        $mimeType = MimeType::fromString('image/png');

        // Act & Assert
        Assert::assertTrue($mimeType->isImage());
        Assert::assertFalse($mimeType->isDocument());
        Assert::assertFalse($mimeType->isArchive());
        Assert::assertFalse($mimeType->isText());
    }

    #[Test]
    public function should_identify_document_mime_type(): void
    {
        // Arrange
        $mimeType = MimeType::fromString('application/pdf');

        // Act & Assert
        Assert::assertFalse($mimeType->isImage());
        Assert::assertTrue($mimeType->isDocument());
        Assert::assertFalse($mimeType->isArchive());
        Assert::assertFalse($mimeType->isText());
    }

    #[Test]
    public function should_identify_archive_mime_type(): void
    {
        // Arrange
        $zip = MimeType::fromString('application/zip');
        $rar = MimeType::fromString('application/x-rar-compressed');

        // Act & Assert
        Assert::assertTrue($zip->isArchive());
        Assert::assertFalse($zip->isDocument());
        Assert::assertTrue($rar->isArchive());
        Assert::assertFalse($rar->isDocument());
    }

    #[Test]
    public function should_identify_text_mime_type(): void
    {
        // Arrange
        $mimeType = MimeType::fromString('text/plain');

        // Act & Assert
        Assert::assertFalse($mimeType->isImage());
        Assert::assertFalse($mimeType->isDocument());
        Assert::assertFalse($mimeType->isArchive());
        Assert::assertTrue($mimeType->isText());
    }

    #[Test]
    public function should_be_equal_when_mime_types_are_same(): void
    {
        // Arrange
        $mimeType1 = MimeType::fromString('image/webp');
        $mimeType2 = MimeType::fromString('image/webp');

        // Act & Assert
        Assert::assertTrue($mimeType1->equals($mimeType2));
        Assert::assertTrue($mimeType2->equals($mimeType1));
    }

    #[Test]
    public function should_not_be_equal_when_mime_types_are_different(): void
    {
        // Arrange
        $mimeType1 = MimeType::fromString('image/jpeg');
        $mimeType2 = MimeType::fromString('image/png');

        // Act & Assert
        Assert::assertFalse($mimeType1->equals($mimeType2));
        Assert::assertFalse($mimeType2->equals($mimeType1));
    }

    #[Test]
    public function should_return_allowed_types_contains_known_values(): void
    {
        // Arrange & Act
        $allowed = MimeType::getAllowedTypes();

        // Assert
        Assert::assertContains('image/jpeg', $allowed);
        Assert::assertContains('application/pdf', $allowed);
        Assert::assertContains('application/zip', $allowed);
        Assert::assertContains('text/plain', $allowed);
    }
}
