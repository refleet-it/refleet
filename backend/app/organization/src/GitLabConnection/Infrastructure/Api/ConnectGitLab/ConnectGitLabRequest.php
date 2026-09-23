<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\ConnectGitLab;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ConnectGitLab',
    description: 'Payload used to connect (or reconnect) the organization to a GitLab group, so its projects can be synced into the fleet.'
)]
final readonly class ConnectGitLabRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'acme-corp/backend-team')]
        public string $groupPath,
        #[Assert\NotBlank]
        #[OA\Property(type: 'string', example: 'glpat-xxxxxxxxxxxxxxxxxxxx')]
        public string $accessToken,
        #[Assert\Length(max: 255)]
        #[Assert\Url]
        #[OA\Property(description: 'Defaults to https://gitlab.com when omitted; set for self-hosted instances.', type: 'string', example: 'https://gitlab.com')]
        public ?string $baseUrl = null,
    ) {
    }
}
