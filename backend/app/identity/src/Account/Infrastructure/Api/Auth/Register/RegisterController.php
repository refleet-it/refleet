<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Register;

use App\Identity\Account\Application\Command\Register\RegisterCommand;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/register', name: 'auth_register', methods: ['POST'])]
#[OA\Post(
    description: 'Register a new account with a unique email and password. Email verification is required before login.',
    summary: 'Register a new account',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Account created successfully. Email verification required.'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'This instance does not accept self-registration'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Email conflict'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many registration attempts'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors'),
    ]
)]
final readonly class RegisterController
{
    private const int MAX_ATTEMPTS = 5;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private RateLimiter $rateLimiter,
        // An instance reachable from the internet should not hand out accounts to whoever
        // finds it. Off by default; the sign-in screen asks RegistrationPolicyController
        // rather than offering a form that would only fail here.
        #[Autowire('%env(bool:ALLOW_SIGNUP)%')]
        private bool $allowSignup,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        RegisterRequest $payload,
        MessageBusInterface $bus,
        Request $request,
    ): JsonResponse {
        if (!$this->allowSignup) {
            return new JsonResponse([
                'message' => 'This instance does not accept self-registration. Ask an administrator for an invitation.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('register', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        $bus->dispatch(new RegisterCommand(
            $payload->email,
            $payload->password,
            RoleEnum::USER,
            (bool) $payload->termsAccepted,
            $payload->marketingConsent,
        ));

        return new JsonResponse([
            'message' => 'Account created successfully. Please check your email to verify your account.',
        ], Response::HTTP_CREATED);
    }
}
