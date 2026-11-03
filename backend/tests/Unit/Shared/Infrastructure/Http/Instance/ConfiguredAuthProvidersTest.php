<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\Instance;

use App\Shared\Infrastructure\Http\Instance\ConfiguredAuthProviders;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredAuthProviders::class)]
final class ConfiguredAuthProvidersTest extends TestCase
{
    #[Test]
    public function reports_nothing_on_an_instance_with_local_accounts_only(): void
    {
        // Which is every instance that has configured no provider — the default, and the
        // reason an unset variable has to mean "off" rather than "boot failure".
        Assert::assertSame([], $this->providers()->toArray());
    }

    #[Test]
    public function reports_google_once_it_has_both_halves_of_a_client(): void
    {
        Assert::assertSame(
            [['provider' => 'google', 'label' => '']],
            $this->providers(googleClientId: 'id', googleClientSecret: 'secret')->toArray(),
        );
    }

    #[Test]
    public function reports_oidc_with_the_name_the_operator_gave_it(): void
    {
        Assert::assertSame(
            [['provider' => 'oidc', 'label' => 'MyBenefit SSO']],
            $this->providers(
                oidcIssuer: 'https://dex.example.test',
                oidcClientId: 'refleet',
                oidcClientSecret: 'secret',
                oidcLabel: 'MyBenefit SSO',
            )->toArray(),
        );
    }

    #[Test]
    public function reports_both_when_both_are_configured(): void
    {
        // Act
        $providers = $this->providers(
            googleClientId: 'id',
            googleClientSecret: 'secret',
            oidcIssuer: 'https://dex.example.test',
            oidcClientId: 'refleet',
            oidcClientSecret: 'secret',
        )->toArray();

        // Assert
        Assert::assertSame(['google', 'oidc'], \array_column($providers, 'provider'));
    }

    #[Test]
    public function treats_a_half_configured_provider_as_not_configured(): void
    {
        // Half a client cannot complete a flow, so offering the button would only produce a
        // failure the person signing in cannot do anything about.
        $cases = [
            'google without a secret' => $this->providers(googleClientId: 'id'),
            'google without an id' => $this->providers(googleClientSecret: 'secret'),
            'oidc without an issuer' => $this->providers(oidcClientId: 'refleet', oidcClientSecret: 'secret'),
            'oidc without an id' => $this->providers(oidcIssuer: 'https://dex.example.test', oidcClientSecret: 'secret'),
            'oidc without a secret' => $this->providers(oidcIssuer: 'https://dex.example.test', oidcClientId: 'refleet'),
        ];

        foreach ($cases as $description => $providers) {
            Assert::assertSame([], $providers->toArray(), $description);
        }
    }

    private function providers(
        string $googleClientId = '',
        string $googleClientSecret = '',
        string $oidcIssuer = '',
        string $oidcClientId = '',
        string $oidcClientSecret = '',
        string $oidcLabel = '',
    ): ConfiguredAuthProviders {
        return new ConfiguredAuthProviders(
            $googleClientId,
            $googleClientSecret,
            $oidcIssuer,
            $oidcClientId,
            $oidcClientSecret,
            $oidcLabel,
        );
    }
}
