<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\CancelShift;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'CancelShift',
    description: 'Payload used to cancel a shift that is not yet in a terminal state.'
)]
final readonly class CancelShiftRequest
{
    public function __construct(
        #[Assert\Length(max: 1000)]
        #[OA\Property(type: 'string', example: 'Superseded by a newer shift')]
        public ?string $reason = null,
    ) {
    }
}
