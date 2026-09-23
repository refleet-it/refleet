<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\HeartbeatRunner;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'RunnerUsageRateLimit',
    description: 'One subscription rate-limit window as the claude CLI reports it (five_hour, seven_day, seven_day:<model>, extra_usage).'
)]
final readonly class RunnerUsageRateLimitRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        #[OA\Property(type: 'string', example: 'five_hour')]
        public string $window,
        #[Assert\Choice(choices: ['allowed', 'allowed_warning', 'rejected'])]
        #[OA\Property(type: 'string', enum: ['allowed', 'allowed_warning', 'rejected'], example: 'allowed')]
        public string $status,
        #[Assert\Range(min: 0, max: 1)]
        #[OA\Property(description: 'Share of the window already consumed, 0..1. Absent when the CLI build does not report it.', type: 'number', format: 'float', example: 0.42)]
        public ?float $utilization = null,
        #[Assert\DateTime(format: \DateTimeInterface::RFC3339_EXTENDED)]
        #[OA\Property(type: 'string', format: 'date-time')]
        public ?string $resetsAt = null,
    ) {
    }

    /**
     * @return array{window: string, status: string, utilization: float|null, resetsAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'window' => $this->window,
            'status' => $this->status,
            'utilization' => $this->utilization,
            'resetsAt' => $this->resetsAt,
        ];
    }
}
