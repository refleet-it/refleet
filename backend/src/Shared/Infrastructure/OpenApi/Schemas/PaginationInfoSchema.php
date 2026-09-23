<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PaginationInfo',
    title: 'Pagination Information',
    description: 'Pagination metadata for list responses'
)]
final readonly class PaginationInfoSchema
{
    public function __construct(
        #[OA\Property(property: 'total', description: 'Total number of items', type: 'integer')]
        public int $total = 0,
        #[OA\Property(property: 'limit', description: 'Number of items per page', type: 'integer')]
        public int $limit = 0,
        #[OA\Property(property: 'offset', description: 'Offset for pagination', type: 'integer')]
        public int $offset = 0,
        #[OA\Property(property: 'nextCursor', description: 'Cursor for next page', type: 'string', nullable: true)]
        public ?string $nextCursor = null,
        #[OA\Property(property: 'hasNextPage', description: 'Whether there are more pages available', type: 'boolean')]
        public bool $hasNextPage = false,
    ) {
    }
}
