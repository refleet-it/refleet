<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Security;

use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabOAuthStateException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The OAuth `state` carries everything the callback needs (who started the flow, for which
 * group) as a signed, expiring blob instead of a server-side session, which this stateless
 * API does not have. Binding it to the account id is what stops a login-CSRF: an attacker
 * who tricks an owner into completing a flow the attacker started fails the account check.
 */
final readonly class GitLabOAuthStateSigner
{
    private const int TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%env(APP_SECRET)%')]
        private string $secret,
    ) {
    }

    public function sign(string $organizationId, string $accountId, string $groupPath): string
    {
        $payload = $this->encode(\json_encode([
            'o' => $organizationId,
            'a' => $accountId,
            'g' => $groupPath,
            'n' => \bin2hex(\random_bytes(8)),
            'e' => \time() + self::TTL_SECONDS,
        ], \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->mac($payload);
    }

    /**
     * @return array{organizationId: string, accountId: string, groupPath: string}
     */
    public function verify(string $state): array
    {
        $parts = \explode('.', $state, 2);

        if (2 !== \count($parts) || !\hash_equals($this->mac($parts[0]), $parts[1])) {
            throw new InvalidGitLabOAuthStateException('the authorization state is not valid');
        }

        $data = $this->decodePayload($parts[0]);

        if ($data['e'] < \time()) {
            throw new InvalidGitLabOAuthStateException('the authorization took too long; start again');
        }

        return ['organizationId' => $data['o'], 'accountId' => $data['a'], 'groupPath' => $data['g']];
    }

    /**
     * @return array{o: string, a: string, g: string, e: int}
     */
    private function decodePayload(string $encoded): array
    {
        $decoded = $this->decode($encoded);
        $data = null === $decoded ? [] : \json_decode($decoded, true);
        $data = \is_array($data) ? $data : [];

        $organizationId = $data['o'] ?? null;
        $accountId = $data['a'] ?? null;
        $groupPath = $data['g'] ?? null;
        $expiresAt = $data['e'] ?? null;

        if (!\is_string($organizationId) || !\is_string($accountId) || !\is_string($groupPath) || !\is_int($expiresAt)) {
            throw new InvalidGitLabOAuthStateException('the authorization state is not valid');
        }

        return ['o' => $organizationId, 'a' => $accountId, 'g' => $groupPath, 'e' => $expiresAt];
    }

    private function mac(string $payload): string
    {
        return $this->encode(\hash_hmac('sha256', $payload, $this->secret, true));
    }

    private function encode(string $raw): string
    {
        return \rtrim(\strtr(\base64_encode($raw), '+/', '-_'), '=');
    }

    private function decode(string $encoded): ?string
    {
        $decoded = \base64_decode(\strtr($encoded, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}
