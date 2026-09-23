<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\CancelQualification;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'CancelQualification',
    description: 'Payload used to cancel a qualification that is not yet in a terminal state.'
)]
final readonly class CancelQualificationRequest
{
    public function __construct(
        #[Assert\Length(max: 1000)]
        #[OA\Property(type: 'string', example: 'Superseded by a newer qualification')]
        public ?string $reason = null,
    ) {
    }
}
