<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command;

use App\Identity\Account\Application\Command\TokensDto;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokensDto::class)]
final class TokensDtoTest extends TestCase
{
    #[Test]
    public function it_keeps_provided_tokens(): void
    {
        // Arrange
        $jwtToken = 'jwt.token.value';
        $refreshToken = 'refresh.token.value';

        // Act
        $dto = new TokensDto($jwtToken, $refreshToken);

        // Assert
        Assert::assertSame($jwtToken, $dto->jwtToken);
        Assert::assertSame($refreshToken, $dto->refreshToken);
    }

    #[Test]
    public function it_allows_null_refresh_token(): void
    {
        // Arrange
        $jwtToken = 'jwt.token.value';

        // Act
        $dto = new TokensDto($jwtToken, null);

        // Assert
        Assert::assertSame($jwtToken, $dto->jwtToken);
        Assert::assertNull($dto->refreshToken);
    }
}
