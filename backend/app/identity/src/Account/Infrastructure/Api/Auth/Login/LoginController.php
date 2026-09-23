<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Login;

use App\Identity\Account\Application\Command\Login\LoginCommand;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Exception\InvalidCredentialsException;
use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Shared\Domain\Exception\TooManyRequestsException;
use OpenApi\Attributes as OA;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/login', name: 'auth_login', methods: ['POST'])]
#[OA\Post(
    description: 'Login user to system',
    summary: 'Login',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Successfully logged in'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many failed attempts'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Bad credentials'),
    ]
)]
final readonly class LoginController
{
    private const int MAX_ATTEMPTS = 10;

    private const int BLOCK_SECONDS = 60;

    public function __construct(
        private RefreshTokenCookieFactory $cookieFactory,
        private AccessTokenCookieFactory $accessTokenCookieFactory,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        LoginRequest $payload,
        MessageBusInterface $bus,
        Request $request,
    ): JsonResponse {
        $ip = $request->getClientIp() ?? 'unknown';
        $hash = \hash('sha256', $ip.'|'.\strtolower($payload->email));
        $blockedKey = 'login_blocked_'.$hash;
        $attemptsKey = 'login_attempts_'.$hash;

        if ($this->cache->getItem($blockedKey)->isHit()) {
            throw new TooManyRequestsException(self::BLOCK_SECONDS);
        }

        try {
            $handledStamp = $bus->dispatch(new LoginCommand(
                email: $payload->email,
                plainPassword: $payload->password
            ))->last(HandledStamp::class);

            $this->clearLoginAttempts($attemptsKey);
        } catch (\Throwable $throwable) {
            $inner = $throwable instanceof HandlerFailedException ? $throwable->getPrevious() : null;

            if ($inner instanceof InvalidCredentialsException) {
                $this->recordFailedAttempt($attemptsKey, $blockedKey);
            }

            throw $throwable;
        }

        /** @var TokensDto|null $tokensDto */
        $tokensDto = $handledStamp?->getResult();

        return $this->buildTokenResponse($tokensDto);
    }

    private function buildTokenResponse(?TokensDto $tokensDto): JsonResponse
    {
        $response = new JsonResponse([
            'token' => [
                'jwtToken' => null !== $tokensDto ? $tokensDto->jwtToken : '',
            ],
        ], Response::HTTP_OK);

        if (null !== $tokensDto && null !== $tokensDto->refreshToken) {
            $response->headers->setCookie($this->cookieFactory->create($tokensDto->refreshToken));
        }

        if (null !== $tokensDto) {
            $response->headers->setCookie($this->accessTokenCookieFactory->create($tokensDto->jwtToken));
        }

        return $response;
    }

    private function recordFailedAttempt(string $attemptsKey, string $blockedKey): void
    {
        $item = $this->cache->getItem($attemptsKey);
        $storedValue = $item->get();
        $attempts = ($item->isHit() && \is_int($storedValue) ? $storedValue : 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $blocked = $this->cache->getItem($blockedKey);
            $blocked->set(true)->expiresAfter(self::BLOCK_SECONDS);
            $this->cache->save($blocked);
            $this->cache->deleteItem($attemptsKey);
        } else {
            $item->set($attempts)->expiresAfter(self::BLOCK_SECONDS);
            $this->cache->save($item);
        }
    }

    private function clearLoginAttempts(string $attemptsKey): void
    {
        $this->cache->deleteItem($attemptsKey);
    }
}
