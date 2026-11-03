<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Domain\Account\Exception\OidcAuthenticationFailedException;
use App\Identity\Account\Infrastructure\Service\OidcHttpReader;
use App\Identity\Account\Infrastructure\Service\OidcJwksProvider;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\IsEqualChecker;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Clock\ClockInterface;

/**
 * Everything that has to be true of an id_token before anything is believed about the person
 * it describes: the signature is the issuer's, and `iss`, `aud`, `exp`, `iat` and `nonce` all
 * say it was minted for this instance and for the sign-in that is in progress.
 *
 * Separate from OidcClient so this reads as the one place where a mistake would be an
 * authentication bypass rather than a failed request.
 */
final readonly class OidcIdTokenVerifier
{
    private const int CLOCK_SKEW_SECONDS = 30;

    public function __construct(
        private OidcJwksProvider $jwks,
        private OidcSignatureAlgorithms $algorithms,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed> the verified claims
     */
    public function verify(string $idToken, string $jwksUri, string $issuer, string $audience, string $expectedNonce): array
    {
        $jws = $this->unserialize($idToken);
        $this->checkAlgorithm($jws);
        $this->checkSignature($jws, $jwksUri);

        $payload = $jws->getPayload();

        if (null === $payload) {
            throw new OidcAuthenticationFailedException('the id_token has no payload');
        }

        $claims = OidcHttpReader::decodeJsonObject($payload, 'the id_token payload is not a JSON object');
        $this->checkClaims($claims, $issuer, $audience, $expectedNonce);

        return $claims;
    }

    private function unserialize(string $idToken): JWS
    {
        $serializer = new CompactSerializer();

        try {
            return $serializer->unserialize($idToken);
        } catch (\Throwable) {
            throw new OidcAuthenticationFailedException('the id_token is not a well-formed JWS');
        }
    }

    /**
     * Refuses `none` and the HMAC family before any key is tried — see
     * OidcSignatureAlgorithms for why that matters more than it looks.
     */
    private function checkAlgorithm(JWS $jws): void
    {
        $headerChecker = new HeaderCheckerManager(
            [new AlgorithmChecker($this->algorithms->names(), protectedHeader: true)],
            [new JWSTokenSupport()],
        );

        try {
            $headerChecker->check($jws, 0, ['alg']);
        } catch (\Throwable) {
            throw new OidcAuthenticationFailedException('the id_token is signed with an algorithm this instance does not accept');
        }
    }

    private function checkSignature(JWS $jws, string $jwksUri): void
    {
        $verifier = new JWSVerifier($this->algorithms->manager());

        // A token signed with a key the cached set predates is a rotation, not a forgery. One
        // forced refetch tells the two apart without letting a stream of bad tokens hammer the
        // issuer's JWKS endpoint.
        if ($verifier->verifyWithKeySet($jws, $this->jwks->keys($jwksUri, false), 0)
            || $verifier->verifyWithKeySet($jws, $this->jwks->keys($jwksUri, true), 0)) {
            return;
        }

        throw new OidcAuthenticationFailedException("the id_token signature does not verify against the issuer's keys");
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function checkClaims(array $claims, string $issuer, string $audience, string $expectedNonce): void
    {
        $claimChecker = new ClaimCheckerManager([
            new IssuerChecker([$issuer]),
            new AudienceChecker($audience),
            new ExpirationTimeChecker($this->clock, self::CLOCK_SKEW_SECONDS),
            new IssuedAtChecker($this->clock, self::CLOCK_SKEW_SECONDS),
            // Without this an id_token the issuer minted for another of its clients, or an
            // older one for this client, would be as good as the one this flow asked for.
            new IsEqualChecker('nonce', $expectedNonce),
        ]);

        try {
            $claimChecker->check($claims, ['iss', 'aud', 'exp', 'iat', 'sub', 'nonce']);
        } catch (\Throwable $throwable) {
            throw new OidcAuthenticationFailedException('the id_token claims were rejected: '.$throwable->getMessage());
        }
    }
}
