<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject\Pagination;

use App\Shared\Domain\Exception\InvalidPaginationParametersException;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaginationParameters::class)]
#[UsesClass(InvalidPaginationParametersException::class)]
final class PaginationParametersTest extends TestCase
{
    #[Test]
    public function constructs_with_defaults_and_exposes_values(): void
    {
        // Act
        $params = new PaginationParameters();

        // Assert
        Assert::assertSame(1, $params->getPage());
        Assert::assertSame(20, $params->getLimit());
        Assert::assertSame(0, $params->getOffset());
        Assert::assertFalse($params->hasPreviousPage());
        Assert::assertFalse($params->hasNextPage(0));
        Assert::assertSame(0, $params->getTotalPages(0));
    }

    #[Test]
    public function from_request_applies_defaults_when_null(): void
    {
        // Act
        $params = PaginationParameters::fromRequest();

        // Assert
        Assert::assertSame(1, $params->getPage());
        Assert::assertSame(20, $params->getLimit());
    }

    #[Test]
    public function from_request_accepts_values(): void
    {
        // Act
        $params = PaginationParameters::fromRequest(page: 3, limit: 10);

        // Assert
        Assert::assertSame(3, $params->getPage());
        Assert::assertSame(10, $params->getLimit());
        Assert::assertSame(20, $params->getOffset());
        Assert::assertTrue($params->hasPreviousPage());
        Assert::assertSame(3, $params->getTotalPages(25));
        Assert::assertFalse($params->hasNextPage(25)); // offset(20)+limit(10)=30 < 25 -> false
        Assert::assertTrue($params->hasNextPage(35));  // 30 < 35 -> true
    }

    #[Test]
    public function rejects_invalid_page_less_than_one(): void
    {
        // Assert
        $this->expectException(InvalidPaginationParametersException::class);
        $this->expectExceptionMessage('Page must be greater than 0');

        // Act
        new PaginationParameters(page: 0, limit: 20);
    }

    #[Test]
    public function rejects_invalid_limit_too_small(): void
    {
        // Assert
        $this->expectException(InvalidPaginationParametersException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        // Act
        new PaginationParameters(page: 1, limit: 0);
    }

    #[Test]
    public function rejects_invalid_limit_too_large(): void
    {
        // Assert
        $this->expectException(InvalidPaginationParametersException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        // Act
        new PaginationParameters(page: 1, limit: 101);
    }

    #[Test]
    public function accepts_boundary_limits(): void
    {
        // Act
        $min = new PaginationParameters(page: 1, limit: 1);
        $max = new PaginationParameters(page: 2, limit: 100);

        // Assert
        Assert::assertSame(1, $min->getLimit());
        Assert::assertSame(100, $max->getLimit());
        Assert::assertSame(100, $max->getOffset()); // (2-1)*100
    }
}
