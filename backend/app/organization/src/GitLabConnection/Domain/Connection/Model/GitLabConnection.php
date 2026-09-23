<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Model;

use App\Organization\GitLabConnection\Domain\Connection\Enum\GitLabSyncStatusEnum;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\AccountId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\GitLabConnectionId;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gitlab_connections', schema: 'organization')]
#[ORM\UniqueConstraint(name: 'uniq_gitlab_connections_organization_id', columns: ['organization_id'])]
class GitLabConnection extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(name: 'connected_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $connectedAt;

    #[ORM\Column(name: 'connected_by_account_id', type: Types::GUID)]
    private string $connectedByAccountId;

    #[ORM\Column(name: 'last_synced_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(name: 'last_sync_status', type: Types::STRING, enumType: GitLabSyncStatusEnum::class)]
    private GitLabSyncStatusEnum $lastSyncStatus = GitLabSyncStatusEnum::NEVER_SYNCED;

    #[ORM\Column(name: 'last_sync_error', type: Types::TEXT, nullable: true)]
    private ?string $lastSyncError = null;

    #[ORM\Column(name: 'last_sync_project_count', type: Types::INTEGER, nullable: true)]
    private ?int $lastSyncProjectCount = null;

    /**
     * Shared secret GitLab must echo back in the X-Gitlab-Token header of every webhook
     * call, so the receiving endpoint (public, unauthenticated by user session) can tell
     * a genuine GitLab delivery for this organization apart from anyone else's request.
     * Generated once at connect() and left untouched by reconnect() — rotating it would
     * silently break a webhook the user already configured in GitLab.
     */
    #[ORM\Column(name: 'webhook_secret', type: Types::STRING, length: 64)]
    private string $webhookSecret;

    private function __construct(
        GitLabConnectionId $id,
        OrganizationId $organizationId,
        #[ORM\Column(name: 'base_url', type: Types::STRING, length: 255)]
        private string $baseUrl,
        #[ORM\Column(name: 'group_id', type: Types::STRING, length: 32)]
        private string $groupId,
        #[ORM\Column(name: 'group_path', type: Types::STRING, length: 255)]
        private string $groupPath,
        #[ORM\Column(name: 'group_name', type: Types::STRING, length: 255)]
        private string $groupName,
        #[ORM\Column(name: 'access_token_ciphertext', type: Types::TEXT)]
        private string $accessTokenCiphertext,
        AccountId $connectedByAccountId,
        /**
         * Only set for OAuth connections. A pasted access token has no refresh token and no
         * known expiry, so both stay null and the access token is used as-is until the user
         * reconnects.
         */
        #[ORM\Column(name: 'refresh_token_ciphertext', type: Types::TEXT, nullable: true)]
        private ?string $refreshTokenCiphertext,
        #[ORM\Column(name: 'access_token_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $accessTokenExpiresAt,
    ) {
        $this->id = $id->asString();
        $this->organizationId = $organizationId->asString();
        $this->connectedAt = new \DateTimeImmutable();
        $this->connectedByAccountId = $connectedByAccountId->asString();
        $this->webhookSecret = \bin2hex(\random_bytes(32));
    }

    public static function connect(
        GitLabConnectionId $id,
        OrganizationId $organizationId,
        string $baseUrl,
        string $groupId,
        string $groupPath,
        string $groupName,
        string $accessTokenCiphertext,
        AccountId $connectedByAccountId,
        ?string $refreshTokenCiphertext = null,
        ?\DateTimeImmutable $accessTokenExpiresAt = null,
    ): self {
        return new self(
            id: $id,
            organizationId: $organizationId,
            baseUrl: $baseUrl,
            groupId: $groupId,
            groupPath: $groupPath,
            groupName: $groupName,
            accessTokenCiphertext: $accessTokenCiphertext,
            connectedByAccountId: $connectedByAccountId,
            refreshTokenCiphertext: $refreshTokenCiphertext,
            accessTokenExpiresAt: $accessTokenExpiresAt,
        );
    }

    /**
     * Re-authorizes an existing connection with a fresh token/group, e.g. when the
     * previous token was rotated or revoked. Resets the sync status since the new
     * credentials haven't synced anything yet.
     */
    public function reconnect(
        string $baseUrl,
        string $groupId,
        string $groupPath,
        string $groupName,
        string $accessTokenCiphertext,
        AccountId $connectedByAccountId,
        ?string $refreshTokenCiphertext = null,
        ?\DateTimeImmutable $accessTokenExpiresAt = null,
    ): void {
        $this->baseUrl = $baseUrl;
        $this->groupId = $groupId;
        $this->groupPath = $groupPath;
        $this->groupName = $groupName;
        $this->accessTokenCiphertext = $accessTokenCiphertext;
        $this->refreshTokenCiphertext = $refreshTokenCiphertext;
        $this->accessTokenExpiresAt = $accessTokenExpiresAt;
        $this->connectedAt = new \DateTimeImmutable();
        $this->connectedByAccountId = $connectedByAccountId->asString();
        $this->lastSyncedAt = null;
        $this->lastSyncStatus = GitLabSyncStatusEnum::NEVER_SYNCED;
        $this->lastSyncError = null;
        $this->lastSyncProjectCount = null;
    }

    /**
     * GitLab rotates the refresh token on every use, so both tokens are replaced together —
     * keeping the old refresh token around would only leave a dead one in the database.
     */
    public function rotateOAuthTokens(string $accessTokenCiphertext, string $refreshTokenCiphertext, \DateTimeImmutable $accessTokenExpiresAt): void
    {
        $this->accessTokenCiphertext = $accessTokenCiphertext;
        $this->refreshTokenCiphertext = $refreshTokenCiphertext;
        $this->accessTokenExpiresAt = $accessTokenExpiresAt;
    }

    public function usesOAuth(): bool
    {
        return null !== $this->refreshTokenCiphertext;
    }

    public function accessTokenExpiresWithin(int $seconds, \DateTimeImmutable $now): bool
    {
        if (null === $this->accessTokenExpiresAt) {
            return false;
        }

        return $this->accessTokenExpiresAt->getTimestamp() - $now->getTimestamp() <= $seconds;
    }

    public function recordSyncSuccess(int $projectCount): void
    {
        $this->lastSyncedAt = new \DateTimeImmutable();
        $this->lastSyncStatus = GitLabSyncStatusEnum::SUCCESS;
        $this->lastSyncError = null;
        $this->lastSyncProjectCount = $projectCount;
    }

    public function recordSyncFailure(string $error): void
    {
        $this->lastSyncedAt = new \DateTimeImmutable();
        $this->lastSyncStatus = GitLabSyncStatusEnum::FAILED;
        $this->lastSyncError = $error;
    }

    public function id(): GitLabConnectionId
    {
        return GitLabConnectionId::fromString($this->id);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function groupPath(): string
    {
        return $this->groupPath;
    }

    public function groupName(): string
    {
        return $this->groupName;
    }

    public function accessTokenCiphertext(): string
    {
        return $this->accessTokenCiphertext;
    }

    public function refreshTokenCiphertext(): ?string
    {
        return $this->refreshTokenCiphertext;
    }

    public function accessTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->accessTokenExpiresAt;
    }

    public function connectedAt(): \DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function connectedByAccountId(): AccountId
    {
        return AccountId::fromString($this->connectedByAccountId);
    }

    public function lastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function lastSyncStatus(): GitLabSyncStatusEnum
    {
        return $this->lastSyncStatus;
    }

    public function lastSyncError(): ?string
    {
        return $this->lastSyncError;
    }

    public function lastSyncProjectCount(): ?int
    {
        return $this->lastSyncProjectCount;
    }

    public function webhookSecret(): string
    {
        return $this->webhookSecret;
    }

    public function hasWebhookSecret(string $providedSecret): bool
    {
        return \hash_equals($this->webhookSecret, $providedSecret);
    }
}
