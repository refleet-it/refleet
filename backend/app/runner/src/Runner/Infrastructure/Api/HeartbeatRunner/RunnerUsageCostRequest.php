<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\HeartbeatRunner;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'RunnerUsageCost', description: 'What the run cost, as the agent estimated it.')]
final readonly class RunnerUsageCostRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        #[OA\Property(type: 'number', format: 'float', example: 0.42)]
        public float $amount,
        #[Assert\Currency]
        #[OA\Property(description: 'ISO 4217 code.', type: 'string', example: 'USD')]
        public string $currency,
    ) {
    }

    /**
     * @return array{amount: float, currency: string}
     */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }
}
