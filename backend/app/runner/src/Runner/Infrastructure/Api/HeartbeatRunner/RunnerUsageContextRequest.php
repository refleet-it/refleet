<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\HeartbeatRunner;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'RunnerUsageContext', description: 'Context window occupancy at the end of the run, in tokens.')]
final readonly class RunnerUsageContextRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        #[OA\Property(type: 'integer', example: 42000)]
        public int $used,
        #[Assert\Positive]
        #[OA\Property(type: 'integer', example: 200000)]
        public int $size,
    ) {
    }

    /**
     * @return array{used: int, size: int}
     */
    public function toArray(): array
    {
        return ['used' => $this->used, 'size' => $this->size];
    }
}
