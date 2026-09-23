<?php

declare(strict_types=1);

namespace App\Tests\Unit\File\File\Infrastructure\Api;

use App\File\File\Infrastructure\Api\UploadFileResponse;
use OpenApi\Attributes as OA;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadFileResponse::class)]
final class UploadFileResponseTest extends TestCase
{
    #[Test]
    public function exposes_all_constructor_values_with_nullables_set(): void
    {
        // Arrange
        $response = new UploadFileResponse(
            id: '550e8400-e29b-41d4-a716-446655440000',
            name: 'invoice.pdf',
            originalName: 'Invoice Final.pdf',
            mimeType: 'application/pdf',
            size: 153600,
            sizeFormatted: '150 KB',
            description: 'Monthly invoice',
            uploaderId: '8f0f5d2e-0182-4c8f-af43-5115165acdf7',
            tenantId: 'f46b7177-e9f2-4f2a-b500-f55bece8f720',
            url: 'https://cdn.example.com/files/invoice.pdf',
            thumbnailUrl: 'https://cdn.example.com/files/invoice-thumb.jpg',
            webpUrl: 'https://cdn.example.com/files/invoice.webp',
            webpThumbnailUrl: 'https://cdn.example.com/files/invoice-thumb.webp',
        );

        // Act
        $actual = [
            'id' => $response->id,
            'name' => $response->name,
            'originalName' => $response->originalName,
            'mimeType' => $response->mimeType,
            'size' => $response->size,
            'sizeFormatted' => $response->sizeFormatted,
            'description' => $response->description,
            'uploaderId' => $response->uploaderId,
            'tenantId' => $response->tenantId,
            'url' => $response->url,
            'thumbnailUrl' => $response->thumbnailUrl,
            'webpUrl' => $response->webpUrl,
            'webpThumbnailUrl' => $response->webpThumbnailUrl,
        ];

        // Assert
        Assert::assertSame(
            [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'name' => 'invoice.pdf',
                'originalName' => 'Invoice Final.pdf',
                'mimeType' => 'application/pdf',
                'size' => 153600,
                'sizeFormatted' => '150 KB',
                'description' => 'Monthly invoice',
                'uploaderId' => '8f0f5d2e-0182-4c8f-af43-5115165acdf7',
                'tenantId' => 'f46b7177-e9f2-4f2a-b500-f55bece8f720',
                'url' => 'https://cdn.example.com/files/invoice.pdf',
                'thumbnailUrl' => 'https://cdn.example.com/files/invoice-thumb.jpg',
                'webpUrl' => 'https://cdn.example.com/files/invoice.webp',
                'webpThumbnailUrl' => 'https://cdn.example.com/files/invoice-thumb.webp',
            ],
            $actual,
        );
    }

    #[Test]
    public function keeps_nullable_fields_as_null_and_preserves_zero_size(): void
    {
        // Arrange
        $response = new UploadFileResponse(
            id: '76d1de6f-b0e6-4ec6-a8ce-37af3e7736cb',
            name: 'empty.txt',
            originalName: 'empty.txt',
            mimeType: 'text/plain',
            size: 0,
            sizeFormatted: '0 B',
            description: null,
            uploaderId: '3f30f903-6b90-4d38-8df9-a76815e1f95f',
            tenantId: null,
            url: 'https://cdn.example.com/files/empty.txt',
            thumbnailUrl: null,
            webpUrl: null,
            webpThumbnailUrl: null,
        );

        // Act
        $result = [
            $response->size,
            $response->description,
            $response->tenantId,
            $response->thumbnailUrl,
            $response->webpUrl,
            $response->webpThumbnailUrl,
        ];

        // Assert
        Assert::assertSame([0, null, null, null, null, null], $result);
    }

    #[Test]
    public function properties_are_readonly_and_cannot_be_modified_after_creation(): void
    {
        // Arrange
        $response = new UploadFileResponse(
            id: '76d1de6f-b0e6-4ec6-a8ce-37af3e7736cb',
            name: 'report.csv',
            originalName: 'report.csv',
            mimeType: 'text/csv',
            size: 12,
            sizeFormatted: '12 B',
            description: null,
            uploaderId: '3f30f903-6b90-4d38-8df9-a76815e1f95f',
            tenantId: null,
            url: 'https://cdn.example.com/files/report.csv',
            thumbnailUrl: null,
            webpUrl: null,
            webpThumbnailUrl: null,
        );

        // Act
        try {
            $response->name = 'changed.csv';
            Assert::fail('Expected Error to be thrown when mutating readonly property.');
        } catch (\Error $error) {
            // Assert
            Assert::assertStringContainsString('readonly', $error->getMessage());
            Assert::assertSame('report.csv', $response->name);
        }
    }

    #[Test]
    public function defines_expected_openapi_schema_properties_and_nullable_flags(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(UploadFileResponse::class);
        $schemaAttrs = $reflection->getAttributes(OA\Schema::class);

        // Act
        $schema = $schemaAttrs[0]->newInstance();
        $properties = $schema->properties ?? [];

        // Assert
        Assert::assertCount(1, $schemaAttrs);
        Assert::assertSame('UploadFileResponse', $schema->title);
        Assert::assertSame('Response model for file upload', $schema->description);
        Assert::assertSame('object', $schema->type);
        Assert::assertCount(13, $properties);

        $metadata = [];
        foreach ($properties as $property) {
            Assert::assertInstanceOf(OA\Property::class, $property);
            $format = $property->format;
            if (\is_string($format) && \str_contains($format, 'Generator::UNDEFINED')) {
                $format = null;
            }

            $metadata[(string) $property->property] = [
                'type' => $property->type,
                'format' => \is_string($format) ? $format : null,
                'nullable' => true === $property->nullable,
            ];
        }

        Assert::assertSame(
            [
                'id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => false],
                'name' => ['type' => 'string', 'format' => null, 'nullable' => false],
                'originalName' => ['type' => 'string', 'format' => null, 'nullable' => false],
                'mimeType' => ['type' => 'string', 'format' => null, 'nullable' => false],
                'size' => ['type' => 'integer', 'format' => null, 'nullable' => false],
                'sizeFormatted' => ['type' => 'string', 'format' => null, 'nullable' => false],
                'description' => ['type' => 'string', 'format' => null, 'nullable' => true],
                'uploaderId' => ['type' => 'string', 'format' => 'uuid', 'nullable' => false],
                'tenantId' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                'url' => ['type' => 'string', 'format' => 'uri', 'nullable' => false],
                'thumbnailUrl' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                'webpUrl' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                'webpThumbnailUrl' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
            ],
            $metadata,
        );
    }
}
