<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Infrastructure\Security;

use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabOAuthStateException;
use App\Organization\GitLabConnection\Infrastructure\Security\GitLabOAuthStateSigner;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitLabOAuthStateSigner::class)]
final class GitLabOAuthStateSignerTest extends TestCase
{
    #[Test]
    public function round_trips_the_signed_payload(): void
    {
        // Arrange
        $signer = new GitLabOAuthStateSigner('app-secret');

        // Act
        $verified = $signer->verify($signer->sign('org-1', 'acc-1', 'acme/backend'));

        // Assert
        Assert::assertSame(['organizationId' => 'org-1', 'accountId' => 'acc-1', 'groupPath' => 'acme/backend'], $verified);
    }

    #[Test]
    public function every_state_is_unique_even_for_the_same_input(): void
    {
        // Arrange
        $signer = new GitLabOAuthStateSigner('app-secret');

        // Act & Assert
        Assert::assertNotSame($signer->sign('org-1', 'acc-1', 'acme'), $signer->sign('org-1', 'acc-1', 'acme'));
    }

    #[Test]
    public function rejects_a_state_signed_with_a_different_secret(): void
    {
        // Arrange
        $state = (new GitLabOAuthStateSigner('other-secret'))->sign('org-1', 'acc-1', 'acme');

        // Assert
        $this->expectException(InvalidGitLabOAuthStateException::class);

        // Act
        (new GitLabOAuthStateSigner('app-secret'))->verify($state);
    }

    #[Test]
    public function rejects_a_tampered_payload(): void
    {
        // Arrange
        $signer = new GitLabOAuthStateSigner('app-secret');
        [$payload, $mac] = \explode('.', $signer->sign('org-1', 'acc-1', 'acme'), 2);
        $forged = \rtrim(\strtr(\base64_encode(\json_encode(['o' => 'org-1', 'a' => 'attacker', 'g' => 'acme', 'n' => 'x', 'e' => \time() + 100], \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        // Assert
        $this->expectException(InvalidGitLabOAuthStateException::class);

        // Act
        $signer->verify($forged.'.'.$mac);
    }

    #[Test]
    public function rejects_garbage(): void
    {
        // Assert
        $this->expectException(InvalidGitLabOAuthStateException::class);

        // Act
        (new GitLabOAuthStateSigner('app-secret'))->verify('not-a-state');
    }
}
