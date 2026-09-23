<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject\Pagination;

use App\Shared\Domain\Exception\InvalidPaginationParametersException;
use App\Shared\Domain\ValueObject\Pagination\CursorPaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CursorPaginationParameters::class)]
#[UsesClass(InvalidPaginationParametersException::class)]
final class CursorPaginationParametersTest extends TestCase
{
    #[Test]
    public function constructs_with_defaults_and_exposes_values(): void
    {
        // Arrange

        // Act
        $params = new CursorPaginationParameters();

        // Assert
        Assert::assertNull($params->getCursor());
        Assert::assertFalse($params->hasCursor());
        Assert::assertSame(6, $params->getLimit());
    }

    #[Test]
    public function from_request_uses_defaults_for_null_values(): void
    {
        // Arrange

        // Act
        $params = CursorPaginationParameters::fromRequest();

        // Assert
        Assert::assertNull($params->getCursor());
        Assert::assertSame(6, $params->getLimit());
    }

    #[Test]
    public function from_request_decodes_valid_cursor(): void
    {
        // Arrange
        $cursor = \base64_encode((string) \json_encode(['id' => 'cursor-123']));

        // Act
        $params = CursorPaginationParameters::fromRequest(cursor: $cursor, limit: 12);

        // Assert
        Assert::assertSame('cursor-123', $params->getCursor());
        Assert::assertTrue($params->hasCursor());
        Assert::assertSame(12, $params->getLimit());
    }

    #[Test]
    public function from_request_ignores_invalid_cursor_payloads(): void
    {
        // Arrange
        $invalidBase64 = '%%%';
        $invalidJson = \base64_encode('not-json');
        $nonStringId = \base64_encode((string) \json_encode(['id' => 123]));

        // Act
        $invalidBase64Params = CursorPaginationParameters::fromRequest(cursor: $invalidBase64);
        $invalidJsonParams = CursorPaginationParameters::fromRequest(cursor: $invalidJson);
        $nonStringIdParams = CursorPaginationParameters::fromRequest(cursor: $nonStringId);
        $emptyCursorParams = CursorPaginationParameters::fromRequest(cursor: '');

        // Assert
        Assert::assertNull($invalidBase64Params->getCursor());
        Assert::assertNull($invalidJsonParams->getCursor());
        Assert::assertNull($nonStringIdParams->getCursor());
        Assert::assertNull($emptyCursorParams->getCursor());
    }

    #[Test]
    public function rejects_limit_below_minimum(): void
    {
        // Arrange
        $thrown = null;

        // Act
        try {
            new CursorPaginationParameters(limit: 0);
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        // Assert
        Assert::assertNotNull($thrown);
        Assert::assertInstanceOf(InvalidPaginationParametersException::class, $thrown);
        Assert::assertSame('Limit must be between 1 and 100', $thrown->getMessage());
    }

    #[Test]
    public function rejects_limit_above_maximum(): void
    {
        // Arrange
        $thrown = null;

        // Act
        try {
            new CursorPaginationParameters(limit: 101);
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        // Assert
        Assert::assertNotNull($thrown);
        Assert::assertInstanceOf(InvalidPaginationParametersException::class, $thrown);
        Assert::assertSame('Limit must be between 1 and 100', $thrown->getMessage());
    }

    #[Test]
    public function accepts_boundary_limits(): void
    {
        // Arrange

        // Act
        $min = new CursorPaginationParameters(limit: 1);
        $max = new CursorPaginationParameters(limit: 100);

        // Assert
        Assert::assertSame(1, $min->getLimit());
        Assert::assertSame(100, $max->getLimit());
    }

    #[Test]
    public function get_next_cursor_returns_null_for_empty_or_invalid_last_item(): void
    {
        // Arrange
        $params = new CursorPaginationParameters();

        // Act
        $fromEmpty = $params->getNextCursor([]);
        $fromScalar = $params->getNextCursor([['id' => 'a'], 42]);

        // Assert
        Assert::assertNull($fromEmpty);
        Assert::assertNull($fromScalar);
    }

    #[Test]
    public function get_next_cursor_encodes_id_from_array_with_custom_field(): void
    {
        // Arrange
        $params = new CursorPaginationParameters();
        $items = [
            ['uuid' => 'first'],
            ['uuid' => 99],
        ];

        // Act
        $nextCursor = $params->getNextCursor($items, 'uuid');

        // Assert
        Assert::assertNotNull($nextCursor);
        $decoded = \base64_decode($nextCursor, true);
        Assert::assertNotFalse($decoded);
        $payload = \json_decode($decoded, true);
        Assert::assertIsArray($payload);
        Assert::assertSame('99', $payload['id']);
        Assert::assertArrayHasKey('timestamp', $payload);
        Assert::assertIsInt($payload['timestamp']);
    }

    #[Test]
    public function get_next_cursor_supports_object_getters_methods_and_public_property(): void
    {
        // Arrange
        $params = new CursorPaginationParameters();
        $withGetter = new class {
            public function getId(): int
            {
                return 10;
            }
        };
        $withIdMethod = new class {
            public function id(): string
            {
                return 'xyz';
            }
        };
        $withPublicProperty = new class {
            public object $uuid;

            public function __construct()
            {
                $this->uuid = new class implements \Stringable {
                    public function __toString(): string
                    {
                        return 'stringable-id';
                    }
                };
            }
        };

        // Act
        $cursorFromGetter = $params->getNextCursor([$withGetter]);
        $cursorFromMethod = $params->getNextCursor([$withIdMethod]);
        $cursorFromProperty = $params->getNextCursor([$withPublicProperty], 'uuid');

        // Assert
        Assert::assertNotNull($cursorFromGetter);
        Assert::assertNotNull($cursorFromMethod);
        Assert::assertNotNull($cursorFromProperty);
        Assert::assertSame('10', \json_decode((string) \base64_decode($cursorFromGetter, true), true)['id']);
        Assert::assertSame('xyz', \json_decode((string) \base64_decode($cursorFromMethod, true), true)['id']);
        Assert::assertSame('stringable-id', \json_decode((string) \base64_decode($cursorFromProperty, true), true)['id']);
    }

    #[Test]
    public function get_next_cursor_returns_null_for_unreadable_or_unsupported_object_id(): void
    {
        // Arrange
        $params = new CursorPaginationParameters();
        $withPrivateProperty = new class {
            private string $id = 'secret';
        };
        $withUnsupportedIdType = new class {
            public function getId(): object
            {
                return new \stdClass();
            }
        };

        // Act
        $privatePropertyCursor = $params->getNextCursor([$withPrivateProperty]);
        $unsupportedTypeCursor = $params->getNextCursor([$withUnsupportedIdType]);

        // Assert
        Assert::assertNull($privatePropertyCursor);
        Assert::assertNull($unsupportedTypeCursor);
    }
}
