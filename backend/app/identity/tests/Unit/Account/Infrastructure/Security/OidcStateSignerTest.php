<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Domain\Account\Exception\InvalidOidcStateException;
use App\Identity\Account\Infrastructure\Security\OidcStateSigner;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OidcStateSigner::class)]
#[UsesClass(InvalidOidcStateException::class)]
final class OidcStateSignerTest extends TestCase
{
    private const string SECRET = 'test-app-secret';

    #[Test]
    public function round_trips_the_nonce_it_was_given(): void
    {
        // Arrange
        $signer = new OidcStateSigner(self::SECRET);
        $nonce = $signer->newNonce();

        // Act
        $nonceBack = $signer->verify($signer->sign($nonce));

        // Assert
        Assert::assertSame($nonce, $nonceBack);
    }

    #[Test]
    public function mints_a_different_nonce_every_time(): void
    {
        // Arrange
        $signer = new OidcStateSigner(self::SECRET);

        // Act
        $nonces = [$signer->newNonce(), $signer->newNonce(), $signer->newNonce()];

        // Assert
        Assert::assertCount(3, \array_unique($nonces));
        foreach ($nonces as $nonce) {
            Assert::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $nonce);
        }
    }

    #[Test]
    public function refuses_a_state_signed_with_another_secret(): void
    {
        // A state another instance issued is not one of ours, and a state nobody signed is
        // the interesting case: that is the forgery this exists to stop.
        // Arrange
        $foreign = new OidcStateSigner('a-different-secret')->sign('nonce-value');

        // Act
        $this->expectException(InvalidOidcStateException::class);

        // Assert
        new OidcStateSigner(self::SECRET)->verify($foreign);
    }

    #[Test]
    public function refuses_a_state_whose_payload_was_tampered_with(): void
    {
        // Arrange
        $signer = new OidcStateSigner(self::SECRET);
        $state = $signer->sign('nonce-value');
        [$payload, $mac] = \explode('.', $state, 2);
        $tampered = $this->encode('{"n":"attacker-nonce","e":'.(\time() + 600).'}').'.'.$mac;

        // Act
        $this->expectException(InvalidOidcStateException::class);

        // Assert
        Assert::assertNotSame($payload, $tampered);
        $signer->verify($tampered);
    }

    #[Test]
    public function refuses_a_state_with_no_signature_at_all(): void
    {
        // Arrange
        $signer = new OidcStateSigner(self::SECRET);

        // Act
        $this->expectException(InvalidOidcStateException::class);

        // Assert
        $signer->verify($this->encode('{"n":"nonce-value","e":'.(\time() + 600).'}'));
    }

    #[Test]
    public function refuses_an_expired_state(): void
    {
        // Arrange
        $signer = new OidcStateSigner(self::SECRET);
        $expired = $this->signedWithPayload('{"n":"nonce-value","e":'.(\time() - 1).'}');

        // Act
        $exception = null;
        try {
            $signer->verify($expired);
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(InvalidOidcStateException::class, $exception);
        Assert::assertStringContainsString('took too long', $exception->getMessage());
    }

    #[Test]
    public function refuses_a_state_whose_payload_is_not_the_expected_shape(): void
    {
        // Arrange
        $signer = new OidcStateSigner(self::SECRET);
        $cases = [
            'not base64 at all' => '!!!not-base64!!!',
            'not json' => $this->encode('not json'),
            'json but not an object' => $this->encode('"a string"'),
            'nonce missing' => $this->encode('{"e":'.(\time() + 600).'}'),
            'nonce empty' => $this->encode('{"n":"","e":'.(\time() + 600).'}'),
            'expiry missing' => $this->encode('{"n":"nonce-value"}'),
            'expiry not an integer' => $this->encode('{"n":"nonce-value","e":"soon"}'),
        ];

        // Act & Assert
        foreach ($cases as $description => $payload) {
            $caught = null;
            try {
                $signer->verify($payload.'.'.$this->mac($payload));
            } catch (\Throwable $throwable) {
                $caught = $throwable;
            }

            Assert::assertInstanceOf(InvalidOidcStateException::class, $caught, $description);
        }
    }

    private function signedWithPayload(string $json): string
    {
        $payload = $this->encode($json);

        return $payload.'.'.$this->mac($payload);
    }

    private function mac(string $payload): string
    {
        return $this->encode(\hash_hmac('sha256', $payload, self::SECRET, true));
    }

    private function encode(string $raw): string
    {
        return \rtrim(\strtr(\base64_encode($raw), '+/', '-_'), '=');
    }
}
