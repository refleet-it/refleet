<?php

declare(strict_types=1);

namespace App\File\File\Infrastructure\Api;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'UploadFileResponse',
    description: 'Response model for file upload',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'originalName', type: 'string'),
        new OA\Property(property: 'mimeType', type: 'string'),
        new OA\Property(property: 'size', type: 'integer'),
        new OA\Property(property: 'sizeFormatted', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'uploaderId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'tenantId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'url', type: 'string', format: 'uri'),
        new OA\Property(property: 'thumbnailUrl', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'webpUrl', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'webpThumbnailUrl', type: 'string', format: 'uri', nullable: true),
    ],
    type: 'object'
)]
final readonly class UploadFileResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public string $sizeFormatted,
        public ?string $description,
        public string $uploaderId,
        public ?string $tenantId,
        public string $url,
        public ?string $thumbnailUrl,
        public ?string $webpUrl,
        public ?string $webpThumbnailUrl,
    ) {
    }
}
