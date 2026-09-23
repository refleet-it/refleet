<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\CompleteGitLabOAuth;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'CompleteGitLabOAuth',
    description: 'The code and state GitLab redirected back with after the owner authorized Refleet.'
)]
final readonly class CompleteGitLabOAuthRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(type: 'string')]
        public string $code,
        #[Assert\NotBlank]
        #[OA\Property(type: 'string')]
        public string $state,
    ) {
    }
}
