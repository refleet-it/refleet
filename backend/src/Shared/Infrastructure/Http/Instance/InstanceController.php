<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Instance;

use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What the frontend has to know about the instance it is talking to, and cannot learn at
 * build time.
 *
 * One browser bundle serves every deployment — the hosted service and every self-hosted
 * instance — so anything baked into it would be baked in for all of them. An address in
 * here compiled into the bundle would have every self-hosted instance telling its users to
 * write to us.
 *
 * Public, and deliberately says nothing an anonymous visitor could not work out by trying
 * to register.
 */
#[Route('/instance', name: 'instance_configuration', methods: ['GET'])]
#[OA\Get(
    description: 'Instance-level settings the frontend needs before a visitor signs in.',
    summary: 'Instance configuration',
    tags: ['Shared'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'The configuration'),
    ]
)]
final readonly class InstanceController
{
    public function __construct(
        #[Autowire('%env(bool:ALLOW_SIGNUP)%')]
        private bool $allowSignup,
        #[Autowire('%env(SUPPORT_EMAIL)%')]
        private string $supportEmail,
        #[Autowire('%env(TERMS_URL)%')]
        private string $termsUrl,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'allowSignup' => $this->allowSignup,
            // Empty means "this instance publishes neither", and the frontend hides the
            // affordance rather than pointing somewhere that is not its operator.
            'supportEmail' => $this->supportEmail,
            'termsUrl' => $this->termsUrl,
        ]);
    }
}
