<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Domain\Account\Exception\InvalidOidcStateException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The OAuth `state` as a signed, expiring blob instead of a server-side session, which this
 * stateless API does not have. Same shape as GitLabOAuthStateSigner in the organization
 * context, reimplemented rather than imported because PHPArkitect does not let one context
 * reach into another.
 *
 * It carries the `nonce` the authorization request was made with, so the callback can insist
 * the id_token it gets back was minted for this flow and not replayed from another one. That
 * is the whole reason the two values travel together: `state` comes back through the browser
 * and is verifiable without storage, and the `nonce` inside it is what the issuer copies into
 * the token.
 *
 * What a signature alone cannot do is tell whose browser started the flow — any state this
 * instance issued verifies, including one an attacker obtained by starting a flow themselves
 * and then feeding the victim the resulting code. OidcFlowCookieFactory closes that by
 * pinning the same state into an HttpOnly cookie, which the callback requires to match.
 */
final readonly class OidcStateSigner
{
    private const int TTL_SECONDS = 600;

    private const int NONCE_RANDOM_BYTES = 16;

    public function __construct(
        #[Autowire('%env(APP_SECRET)%')]
        private string $secret,
    ) {
    }

    public function newNonce(): string
    {
        return \bin2hex(\random_bytes(self::NONCE_RANDOM_BYTES));
    }

    public function sign(string $nonce): string
    {
        $payload = $this->encode(\json_encode([
            'n' => $nonce,
            'e' => \time() + self::TTL_SECONDS,
        ], \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->mac($payload);
    }

    /**
     * @return string the nonce the authorization request was made with
     *
     * @throws InvalidOidcStateException
     */
    public function verify(string $state): string
    {
        $parts = \explode('.', $state, 2);

        if (2 !== \count($parts) || !\hash_equals($this->mac($parts[0]), $parts[1])) {
            throw new InvalidOidcStateException('the sign-in request is not valid');
        }

        $data = $this->decodePayload($parts[0]);

        if ($data['e'] < \time()) {
            throw new InvalidOidcStateException('the sign-in took too long; start again');
        }

        return $data['n'];
    }

    /**
     * @return array{n: string, e: int}
     */
    private function decodePayload(string $encoded): array
    {
        $decoded = $this->decode($encoded);
        $data = null === $decoded ? [] : \json_decode($decoded, true);
        $data = \is_array($data) ? $data : [];

        $nonce = $data['n'] ?? null;
        $expiresAt = $data['e'] ?? null;

        if (!\is_string($nonce) || '' === $nonce || !\is_int($expiresAt)) {
            throw new InvalidOidcStateException('the sign-in request is not valid');
        }

        return ['n' => $nonce, 'e' => $expiresAt];
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
