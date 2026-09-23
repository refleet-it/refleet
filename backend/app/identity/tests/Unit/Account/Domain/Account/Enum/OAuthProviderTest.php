<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Enum;

use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OAuthProvider::class)]
final class OAuthProviderTest extends TestCase
{
    #[Test]
    public function local_provider_is_local_and_not_google(): void
    {
        // Arrange
        $provider = OAuthProvider::LOCAL;

        // Act
        $isLocal = $provider->isLocal();
        $isGoogle = $provider->isGoogle();

        // Assert
        Assert::assertTrue($isLocal);
        Assert::assertFalse($isGoogle);
    }

    #[Test]
    public function google_provider_is_google_and_not_local(): void
    {
        // Arrange
        $provider = OAuthProvider::GOOGLE;

        // Act
        $isGoogle = $provider->isGoogle();
        $isLocal = $provider->isLocal();

        // Assert
        Assert::assertTrue($isGoogle);
        Assert::assertFalse($isLocal);
    }

    #[Test]
    public function exactly_one_provider_predicate_is_true_for_each_case(): void
    {
        // Arrange
        $cases = OAuthProvider::cases();
        $truthTable = [];

        // Act
        foreach ($cases as $case) {
            $truthTable[$case->value] = [
                $case->isLocal(),
                $case->isGoogle(),
            ];
        }

        // Assert
        Assert::assertSame(
            [
                'local' => [true, false],
                'google' => [false, true],
            ],
            $truthTable,
        );
    }

    #[Test]
    public function backed_enum_values_are_stable_and_parseable(): void
    {
        // Arrange
        $invalid = 'github';

        // Act
        $local = OAuthProvider::from('local');
        $google = OAuthProvider::from('google');
        $invalidTryFrom = OAuthProvider::tryFrom($invalid);

        // Assert
        Assert::assertSame('local', OAuthProvider::LOCAL->value);
        Assert::assertSame('google', OAuthProvider::GOOGLE->value);
        Assert::assertSame(OAuthProvider::LOCAL, $local);
        Assert::assertSame(OAuthProvider::GOOGLE, $google);
        Assert::assertNull($invalidTryFrom);
    }

    #[Test]
    public function from_throws_value_error_for_invalid_backed_value(): void
    {
        // Arrange
        $caught = null;

        // Act
        try {
            OAuthProvider::from('facebook');
        } catch (\ValueError $valueError) {
            $caught = $valueError;
        }

        // Assert
        Assert::assertInstanceOf(\ValueError::class, $caught);
        Assert::assertStringContainsString('facebook', $caught?->getMessage() ?? '');
    }
}
