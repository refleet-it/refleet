<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\OpenApi\Schemas;

use App\Shared\Infrastructure\OpenApi\Schemas\PaginationInfoSchema;
use OpenApi\Attributes as OA;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaginationInfoSchema::class)]
final class PaginationInfoSchemaTest extends TestCase
{
    #[Test]
    public function constructs_with_defaults(): void
    {
        // Act
        $schema = new PaginationInfoSchema();

        // Assert
        Assert::assertSame(0, $schema->total);
        Assert::assertSame(0, $schema->limit);
        Assert::assertSame(0, $schema->offset);
        Assert::assertNull($schema->nextCursor);
        Assert::assertFalse($schema->hasNextPage);
    }

    #[Test]
    public function constructs_with_values(): void
    {
        // Arrange
        $total = 123;
        $limit = 25;
        $offset = 100;
        $nextCursor = 'abc123';
        $hasNextPage = true;

        // Act
        $schema = new PaginationInfoSchema(
            total: $total,
            limit: $limit,
            offset: $offset,
            nextCursor: $nextCursor,
            hasNextPage: $hasNextPage,
        );

        // Assert
        Assert::assertSame($total, $schema->total);
        Assert::assertSame($limit, $schema->limit);
        Assert::assertSame($offset, $schema->offset);
        Assert::assertSame($nextCursor, $schema->nextCursor);
        Assert::assertTrue($schema->hasNextPage);
    }

    #[Test]
    public function has_expected_openapi_attributes(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(PaginationInfoSchema::class);

        // Assert class-level schema attribute
        $schemaAttrs = $reflection->getAttributes(OA\Schema::class);
        Assert::assertCount(1, $schemaAttrs);
        $schemaArgs = $schemaAttrs[0]->getArguments();
        Assert::assertSame('PaginationInfo', $schemaArgs['schema'] ?? null);
        Assert::assertSame('Pagination Information', $schemaArgs['title'] ?? null);
        Assert::assertSame('Pagination metadata for list responses', $schemaArgs['description'] ?? null);

        // Assert property-level attributes
        $this->assertPropertyHasOA('total', 'integer', false, 'Total number of items');
        $this->assertPropertyHasOA('limit', 'integer', false, 'Number of items per page');
        $this->assertPropertyHasOA('offset', 'integer', false, 'Offset for pagination');
        $this->assertPropertyHasOA('nextCursor', 'string', true, 'Cursor for next page');
        $this->assertPropertyHasOA('hasNextPage', 'boolean', false, 'Whether there are more pages available');
    }

    private function assertPropertyHasOA(string $property, string $type, bool $nullable, string $description): void
    {
        $prop = new \ReflectionProperty(PaginationInfoSchema::class, $property);
        $attrs = $prop->getAttributes(OA\Property::class);
        Assert::assertCount(1, $attrs, \sprintf('Expected one OA\Property on %s', $property));
        $args = $attrs[0]->getArguments();

        // Verify declared OpenAPI metadata
        Assert::assertSame($property, $args['property'] ?? null, \sprintf('property name mismatch on %s', $property));
        Assert::assertSame($type, $args['type'] ?? null, \sprintf('type mismatch on %s', $property));
        Assert::assertSame($nullable, (bool) ($args['nullable'] ?? false), \sprintf('nullable mismatch on %s', $property));
        Assert::assertSame($description, $args['description'] ?? null, \sprintf('description mismatch on %s', $property));
    }
}
