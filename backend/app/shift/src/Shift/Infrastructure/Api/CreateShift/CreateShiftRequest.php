<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\CreateShift;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'CreateShift',
    description: 'Payload used to draft a new shift. Provide qualificationId alone to target every currently qualified project of that qualification; qualificationId + projectIds to target specific projects of that qualification regardless of status; or projectIds alone (no qualificationId) for a fully manual selection.'
)]
final readonly class CreateShiftRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'Bump acme/legacy-lib to v3')]
        public string $title,
        #[OA\Property(type: 'string', example: 'Removes the deprecated acme/legacy-lib dependency across the fleet.')]
        public ?string $description = null,
        #[Assert\Uuid]
        #[OA\Property(description: 'Source qualification. Omit for a fully manual shift.', type: 'string', format: 'uuid')]
        public ?string $qualificationId = null,
        /**
         * @var string[]|null
         */
        #[Assert\All([new Assert\Uuid()])]
        #[OA\Property(description: 'Explicit project IDs to target. With qualificationId: a specific subset of its targets. Without qualificationId: required, a fully manual selection.', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))]
        public ?array $projectIds = null,
    ) {
    }
}
