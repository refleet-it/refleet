<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Model;

use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The identifier a provider gave this account, which is what the provider promises is stable.
 *
 * `oauthProviders` on Account only records that a provider is attached; it cannot answer "which
 * account is this person" because it holds no identifier. Matching on the email address instead
 * works right up to the moment an address changes hands at the provider, at which point the new
 * owner inherits the old owner's account. The unique constraint on (provider, subject) is what
 * makes that impossible, and it is also why a subject is stored rather than derived.
 */
#[ORM\Entity]
#[ORM\Table(name: 'account_oauth_identities', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'uniq_account_oauth_identities_provider_subject', columns: ['provider', 'subject'])]
class AccountOAuthIdentity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'oauthIdentities')]
        #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Account $account,
        #[ORM\Column(type: Types::STRING, enumType: OAuthProvider::class)]
        private OAuthProvider $provider,
        #[ORM\Column(type: Types::STRING)]
        private string $subject,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function link(
        Id $id,
        Account $account,
        OAuthProvider $provider,
        string $subject,
    ): self {
        return new self(
            id: $id->asString(),
            account: $account,
            provider: $provider,
            subject: $subject,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function id(): Id
    {
        return Id::fromString($this->id);
    }

    public function account(): Account
    {
        return $this->account;
    }

    public function provider(): OAuthProvider
    {
        return $this->provider;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function matches(OAuthProvider $provider, string $subject): bool
    {
        return $this->provider === $provider && $this->subject === $subject;
    }
}
