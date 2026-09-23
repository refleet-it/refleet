<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\StartGitLabOAuth;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'StartGitLabOAuth',
    description: 'Payload used to begin authorizing Refleet on gitlab.com for one group.'
)]
final readonly class StartGitLabOAuthRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'acme-corp/backend-team')]
        public string $groupPath,
    ) {
    }
}
