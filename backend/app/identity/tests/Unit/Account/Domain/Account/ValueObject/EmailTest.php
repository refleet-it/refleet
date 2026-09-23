<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\ValueObject;

use App\Identity\Account\Domain\Account\ValueObject\Email;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Email::class)]
final class EmailTest extends TestCase
{
    #[Test]
    public function should_create_email_from_valid_string(): void
    {
        // Arrange
        $input = 'user@example.com';
        $expected = 'user@example.com';

        // Act
        $email = Email::fromString($input);

        // Assert
        Assert::assertSame($expected, $email->asString());
        Assert::assertSame($expected, (string) $email);
    }

    #[Test]
    public function should_throw_exception_when_email_is_invalid(): void
    {
        // Arrange
        $invalid = 'not-an-email';

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');
        Email::fromString($invalid);
    }

    #[Test]
    public function should_be_equal_when_emails_are_same(): void
    {
        // Arrange
        $email1 = Email::fromString('same@example.com');
        $email2 = Email::fromString('same@example.com');

        // Act
        $result = $email1->equals($email2);

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function should_not_be_equal_when_emails_are_different(): void
    {
        // Arrange
        $email1 = Email::fromString('first@example.com');
        $email2 = Email::fromString('second@example.com');

        // Act
        $result = $email1->equals($email2);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function should_reject_email_with_leading_or_trailing_whitespace(): void
    {
        // Arrange
        $invalid = ' user@example.com ';

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString($invalid);
    }
}
