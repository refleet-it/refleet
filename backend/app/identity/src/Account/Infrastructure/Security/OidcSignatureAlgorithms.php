<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\PS384;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;

/**
 * Which signature algorithms an id_token may be signed with, in one place so the header check
 * and the verifier cannot drift apart — a name accepted by one but missing from the other is
 * either a sign-in that fails for no visible reason or, the wrong way round, a check that
 * does nothing.
 *
 * Asymmetric only, and deliberately. An HMAC algorithm would let a token signed with the
 * client secret — which both sides hold — pass as one the issuer signed, and `none` would let
 * an unsigned token through. Every provider worth pointing an instance at signs with RS256;
 * the rest are here because they cost nothing to accept and a provider configured for one of
 * them would otherwise be unusable.
 */
final readonly class OidcSignatureAlgorithms
{
    /**
     * @return list<SignatureAlgorithm>
     */
    public function all(): array
    {
        return [
            new RS256(), new RS384(), new RS512(),
            new PS256(), new PS384(), new PS512(),
            new ES256(), new ES384(), new ES512(),
        ];
    }

    public function manager(): AlgorithmManager
    {
        return new AlgorithmManager($this->all());
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return \array_map(static fn (SignatureAlgorithm $algorithm): string => $algorithm->name(), $this->all());
    }
}
