<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Notification\Domain\ValueObject;

use App\Notification\Notification\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends TestCase
{
    private const string VALID_EMAIL = 'john.doe@example.com';

    private const string ANOTHER_VALID_EMAIL = 'jane.doe@example.com';

    #[Test]
    public function from_string_returns_email_address_with_same_value(): void
    {
        // Arrange
        $input = self::VALID_EMAIL;

        // Act
        $emailAddress = EmailAddress::fromString($input);

        // Assert
        Assert::assertSame($input, $emailAddress->value());
        Assert::assertSame($input, (string) $emailAddress);
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function from_string_throws_exception_for_invalid_email(string $invalidEmail): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);

        EmailAddress::fromString($invalidEmail);
    }

    #[Test]
    public function equals_returns_true_when_values_match(): void
    {
        // Arrange
        $first = EmailAddress::fromString(self::VALID_EMAIL);
        $second = EmailAddress::fromString(self::VALID_EMAIL);

        // Act & Assert
        Assert::assertTrue($first->equals($second));
        Assert::assertTrue($second->equals($first));
    }

    #[Test]
    public function equals_returns_false_when_values_differ(): void
    {
        // Arrange
        $first = EmailAddress::fromString(self::VALID_EMAIL);
        $second = EmailAddress::fromString(self::ANOTHER_VALID_EMAIL);

        // Act & Assert
        Assert::assertFalse($first->equals($second));
        Assert::assertFalse($second->equals($first));
    }

    public static function invalidEmailProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'missing domain' => ['john.doe@'];
        yield 'missing local part' => ['@example.com'];
        yield 'missing at symbol' => ['john.doe.example.com'];
        yield 'double at symbol' => ['john@@example.com'];
        yield 'invalid characters' => ['john doe@example.com'];
    }
}
