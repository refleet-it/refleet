<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Model;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Event\AccountCreated;
use App\Identity\Account\Domain\Account\Event\AccountUpdated;
use App\Identity\Account\Domain\Account\Event\EmailVerificationRequested;
use App\Identity\Account\Domain\Account\Event\EmailVerified;
use App\Identity\Account\Domain\Account\Event\PasswordChanged;
use App\Identity\Account\Domain\Account\Event\PasswordResetCompleted;
use App\Identity\Account\Domain\Account\Event\PasswordResetRequested;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'accounts', schema: 'identity')]
class Account extends AggregateRoot
{
    private const int TOKEN_RANDOM_BYTES = 32;

    private const string EMAIL_VERIFICATION_EXPIRY = '+12 hours';

    private const string PASSWORD_RESET_EXPIRY = '+1 hour';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $passwordHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $termsAcceptedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $marketingConsentAt = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $oauthProviders = [];

    /**
     * @var Collection<int, RefreshToken>
     */
    #[ORM\OneToMany(targetEntity: RefreshToken::class, mappedBy: 'account', cascade: ['persist', 'remove'])]
    private Collection $refreshTokens;

    /**
     * @var Collection<int, PasswordResetToken>
     */
    #[ORM\OneToMany(targetEntity: PasswordResetToken::class, mappedBy: 'account', cascade: ['persist', 'remove'])]
    private Collection $passwordResetTokens;

    /**
     * @var Collection<int, EmailVerificationToken>
     */
    #[ORM\OneToMany(targetEntity: EmailVerificationToken::class, mappedBy: 'account', cascade: ['persist', 'remove'])]
    private Collection $emailVerificationTokens;

    private function __construct(
        AccountId $id,
        Email $email,
        HashedPassword $hashedPassword,
        #[ORM\Column(type: Types::STRING, enumType: RoleEnum::class)]
        private RoleEnum $role,
        #[ORM\Column(type: Types::STRING, enumType: AccountStatusEnum::class)]
        private AccountStatusEnum $status = AccountStatusEnum::PENDING_EMAIL_VERIFICATION,
        bool $marketingConsent = false,
        OAuthProvider $initialProvider = OAuthProvider::LOCAL,
    ) {
        $this->id = $id->asString();
        $this->email = \mb_strtolower($email->asString());
        $this->passwordHash = $hashedPassword->asString();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->termsAcceptedAt = new \DateTimeImmutable();
        $this->marketingConsentAt = $marketingConsent ? new \DateTimeImmutable() : null;
        $this->oauthProviders = [$initialProvider->value];
        $this->refreshTokens = new ArrayCollection();
        $this->passwordResetTokens = new ArrayCollection();
        $this->emailVerificationTokens = new ArrayCollection();
    }

    public function deactivate(): void
    {
        $this->status = AccountStatusEnum::DEACTIVATED;
        $this->updateVersion();
    }

    public function activate(): void
    {
        $this->status = AccountStatusEnum::ACTIVE;
        $this->updateVersion();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isDeleted(): bool
    {
        return $this->status->isDeactivated();
    }

    public function status(): AccountStatusEnum
    {
        return $this->status;
    }

    public function verifyEmail(): void
    {
        $this->status = AccountStatusEnum::ACTIVE;
        $this->emailVerificationTokens->clear();
        $this->recordThat(new EmailVerified($this->id(), $this->email));
        $this->updateVersion();
    }

    public function requestEmailVerification(): EmailVerificationToken
    {
        $this->emailVerificationTokens->clear();

        $token = EmailVerificationToken::create(
            id: Id::generate(),
            account: $this,
            token: \bin2hex(\random_bytes(self::TOKEN_RANDOM_BYTES)),
            expiresAt: new \DateTimeImmutable(self::EMAIL_VERIFICATION_EXPIRY)
        );

        $this->emailVerificationTokens->add($token);
        $this->recordThat(new EmailVerificationRequested($this->id(), $this->email, $token->token()));

        return $token;
    }

    public function role(): RoleEnum
    {
        return $this->role;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function generateRefreshToken(string $hashedToken, \DateTimeImmutable $expiresAt, ?string $impersonatorId = null): RefreshToken
    {
        $token = RefreshToken::create(
            id: RefreshTokenId::generate(),
            account: $this,
            token: $hashedToken,
            expiresAt: $expiresAt,
            impersonatorId: $impersonatorId,
        );

        $this->refreshTokens->clear();
        $this->refreshTokens->add($token);

        return $token;
    }

    public static function create(
        AccountId $id,
        Email $email,
        HashedPassword $hashedPassword,
        RoleEnum $role,
        AccountStatusEnum $status = AccountStatusEnum::PENDING_EMAIL_VERIFICATION,
        bool $marketingConsent = false,
    ): self {
        $account = new self(
            $id,
            $email,
            $hashedPassword,
            $role,
            $status,
            $marketingConsent
        );

        $account->recordThat(new AccountCreated($id, $email->asString()));

        return $account;
    }

    public function requestPasswordReset(): PasswordResetToken
    {
        $this->passwordResetTokens->clear();

        $token = PasswordResetToken::create(
            id: Id::generate(),
            account: $this,
            token: \bin2hex(\random_bytes(self::TOKEN_RANDOM_BYTES)),
            expiresAt: new \DateTimeImmutable(self::PASSWORD_RESET_EXPIRY)
        );

        $this->passwordResetTokens->add($token);
        $this->recordThat(new PasswordResetRequested($this->id(), $this->email, $token->token()));

        return $token;
    }

    public function id(): AccountId
    {
        return AccountId::fromString($this->id);
    }

    public function resetPassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
        $this->passwordResetTokens->clear();
        // After successful password reset, activate the account if it was pending verification
        if ($this->status->isPendingEmailVerification()) {
            $this->status = AccountStatusEnum::ACTIVE;
        }

        $this->recordThat(new PasswordResetCompleted($this->id(), $this->email));
        $this->updateVersion();
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
        $this->recordThat(new PasswordChanged($this->id(), $this->email));
        $this->updateVersion();
    }

    public function version(): int
    {
        return $this->version;
    }

    public function termsAcceptedAt(): \DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function marketingConsentAt(): ?\DateTimeImmutable
    {
        return $this->marketingConsentAt;
    }

    public function hasMarketingConsent(): bool
    {
        return null !== $this->marketingConsentAt;
    }

    public function addOAuthProvider(OAuthProvider $provider): void
    {
        if ($this->hasOAuthProvider($provider)) {
            return;
        }

        $this->oauthProviders[] = $provider->value;
        $this->updateVersion();
    }

    public function hasOAuthProvider(OAuthProvider $provider): bool
    {
        return \in_array($provider->value, $this->oauthProviders, true);
    }

    /**
     * Gets all OAuth providers for this account.
     *
     * @return array<OAuthProvider>
     */
    public function oauthProviders(): array
    {
        return \array_map(
            OAuthProvider::from(...),
            $this->oauthProviders
        );
    }

    /**
     * Creates account from OAuth provider (e.g., Google).
     * The account is created as ACTIVE since OAuth providers verify email.
     */
    public static function createFromOAuth(
        AccountId $id,
        Email $email,
        OAuthProvider $provider,
        RoleEnum $role = RoleEnum::USER,
        bool $marketingConsent = false,
    ): self {
        // For OAuth accounts, we don't need email verification
        // We also generate a random password hash that cannot be used for login
        $randomPasswordHash = HashedPassword::fromString(\bin2hex(\random_bytes(self::TOKEN_RANDOM_BYTES)));

        $account = new self(
            $id,
            $email,
            $randomPasswordHash,
            $role,
            AccountStatusEnum::ACTIVE,
            $marketingConsent,
            $provider
        );

        $account->recordThat(new AccountCreated($id, $email->asString()));

        return $account;
    }

    private function updateVersion(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        ++$this->version;
        $this->recordThat(new AccountUpdated($this->id(), $this->email, $this->version));
    }
}
