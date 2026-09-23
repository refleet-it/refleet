<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\HeartbeatRunner;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'RunnerUsageTokens', description: 'Tokens billed for the whole run; input counts prompt-cache reads and writes too.')]
final readonly class RunnerUsageTokensRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        #[OA\Property(type: 'integer', example: 120000)]
        public int $input,
        #[Assert\PositiveOrZero]
        #[OA\Property(type: 'integer', example: 3200)]
        public int $output,
    ) {
    }

    /**
     * @return array{input: int, output: int}
     */
    public function toArray(): array
    {
        return ['input' => $this->input, 'output' => $this->output];
    }
}
