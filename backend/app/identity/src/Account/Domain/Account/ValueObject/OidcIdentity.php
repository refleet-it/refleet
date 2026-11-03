<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\ValueObject;

/**
 * What an OIDC issuer said about the person who just signed in, once the id_token has been
 * verified.
 *
 * `subject` is the `sub` claim: the identifier the issuer promises is stable for this person,
 * and the only one worth storing. The email address is useful for matching an account that
 * already exists and for creating one, but it is not an identity — it can change hands.
 *
 * There is no `emailVerified` here on purpose. A token whose email the issuer has not marked
 * verified never becomes an OidcIdentity, so carrying the flag would invite a second,
 * weaker check somewhere downstream.
 */
final readonly class OidcIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public string $name,
    ) {
    }
}
