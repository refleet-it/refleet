<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\HeartbeatRunner;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'RunnerUsage',
    description: "What the runner's last agent run reported about its consumption. Every section is optional: subscription rate limits come only from the claude CLI (stream-json rate_limit_event), the context window from ACP's usage_update (kiro) or the closing stream-json result (claude)."
)]
final readonly class RunnerUsageRequest
{
    /**
     * @param RunnerUsageRateLimitRequest[]|null $rateLimits
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DateTimeInterface::RFC3339_EXTENDED)]
        #[OA\Property(description: 'When the run finished.', type: 'string', format: 'date-time')]
        public string $observedAt,
        #[Assert\Valid]
        #[Assert\Count(max: 16)]
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: RunnerUsageRateLimitRequest::class)))]
        public ?array $rateLimits = null,
        #[Assert\Valid]
        public ?RunnerUsageContextRequest $context = null,
        #[Assert\Valid]
        public ?RunnerUsageTokensRequest $tokens = null,
        #[Assert\Valid]
        public ?RunnerUsageCostRequest $cost = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'observedAt' => $this->observedAt,
            'rateLimits' => null === $this->rateLimits ? null : \array_map(static fn (RunnerUsageRateLimitRequest $window): array => $window->toArray(), $this->rateLimits),
            'context' => $this->context?->toArray(),
            'tokens' => $this->tokens?->toArray(),
            'cost' => $this->cost?->toArray(),
        ];
    }
}
