<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\Runner\Model;

use App\Runner\Runner\Domain\Runner\Enum\RunnerStatusEnum;
use App\Runner\Runner\Domain\Runner\Exception\RunnerAlreadyArchivedException;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerVersion;
use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A registry entry identifying a member of the runner fleet. Claiming/reporting job results (see
 * RunnerJob) only ever references a runner by its free-text id — this aggregate does
 * not gate that behaviour, it exists so runners can be listed and identified.
 */
#[ORM\Entity]
#[ORM\Table(name: 'runners', schema: 'runner')]
#[ORM\Index(name: 'idx_runners_organization_id', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_runners_archived_at', columns: ['archived_at'])]
class Runner extends AggregateRoot
{
    // Nothing ever demotes a stale runner's stored status back down on its own — a
    // runner that stops heartbeating (crashed, network partition, etc.) would otherwise
    // show as online/idle forever. Tolerates a couple of missed beats at the default
    // 30s HEARTBEAT_INTERVAL_SECONDS before treating it as offline for display.
    private const int STALE_AFTER_SECONDS = 90;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(name: 'organization_id', type: Types::GUID)]
    private string $organizationId;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'archived_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    /**
     * What the runner's last agent run reported about its consumption: rate-limit
     * windows, context occupancy, tokens and cost (runner/agent/src/backends/types.ts
     * AgentUsage). Stored as sent — the engines disagree on what they can report and
     * the dashboard shows whichever sections are present. Null until a job has run.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $usage = null;

    /** The runner package version the process reported on its last heartbeat; null before it ever did. */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $version = null;

    /**
     * Set from the dashboard, delivered to the runner on its next heartbeat and cleared in
     * the same breath — a one-shot signal, so a supervisor that brings the same version back
     * does not restart the runner over and over.
     */
    #[ORM\Column(name: 'update_requested_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updateRequestedAt = null;

    private function __construct(
        RunnerId $id,
        OrganizationId $organizationId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $name,
        #[ORM\Column(type: Types::STRING, enumType: RunnerStatusEnum::class)]
        private RunnerStatusEnum $status,
        #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $lastSeenAt,
        // Identifies the API key this runner authenticates with, so archiving can retire
        // exactly that credential. Refreshed on every heartbeat, which keeps it pointing at
        // the key actually in use after a rotation.
        #[ORM\Column(name: 'api_key_id', type: Types::GUID, nullable: true)]
        private ?string $apiKeyId = null,
        /**
         * The AI-mode engines (claude/kiro) this runner auto-detected on its host — see
         * runner/agent/src/detect.ts. Reported on heartbeat and shown on the runner
         * detail page; a runner that has never heartbeat with this field carries null.
         *
         * @var string[]|null
         */
        #[ORM\Column(name: 'supported_engines', type: Types::JSON, nullable: true)]
        private ?array $supportedEngines = null,
        /**
         * Model ids this runner was configured to offer for its engine(s) —
         * operator-declared via CLAUDE_AVAILABLE_MODELS/KIRO_AVAILABLE_MODELS, not
         * auto-detected (neither the claude CLI nor kiro-cli's ACP implementation expose
         * a way to list this at runtime). Reported on heartbeat; a runner that has never
         * heartbeat with this field, or was not configured with it, carries null.
         *
         * @var string[]|null
         */
        #[ORM\Column(name: 'supported_models', type: Types::JSON, nullable: true)]
        private ?array $supportedModels = null,
    ) {
        $this->id = $id->asString();
        $this->organizationId = $organizationId->asString();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param string[]|null $supportedEngines
     * @param string[]|null $supportedModels
     */
    public static function register(
        RunnerId $id,
        OrganizationId $organizationId,
        string $name,
        RunnerStatusEnum $status = RunnerStatusEnum::OFFLINE,
        ?\DateTimeImmutable $lastSeenAt = null,
        ?string $apiKeyId = null,
        ?array $supportedEngines = null,
        ?array $supportedModels = null,
    ): self {
        return new self($id, $organizationId, $name, $status, $lastSeenAt, $apiKeyId, $supportedEngines, $supportedModels);
    }

    public function id(): RunnerId
    {
        return RunnerId::fromString($this->id);
    }

    public function organizationId(): OrganizationId
    {
        return OrganizationId::fromString($this->organizationId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): RunnerStatusEnum
    {
        return $this->status;
    }

    public function lastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    /**
     * Heartbeat only ever knows that the runner is reachable, never whether it is
     * currently working a job (see effectiveStatus()) — so it always resets the stored
     * status to the idle baseline.
     *
     * @param string[]|null             $supportedEngines
     * @param string[]|null             $supportedModels
     * @param array<string, mixed>|null $usage
     */
    public function heartbeat(\DateTimeImmutable $at, ?string $apiKeyId = null, ?array $supportedEngines = null, ?array $supportedModels = null, ?array $usage = null, ?string $version = null): void
    {
        $this->status = RunnerStatusEnum::IDLE;
        $this->lastSeenAt = $at;

        // A browser session can reach the heartbeat endpoint too, and it carries neither
        // — don't let that erase what the real runner process last reported.
        if (null !== $apiKeyId) {
            $this->apiKeyId = $apiKeyId;
        }

        if (null !== $supportedEngines) {
            $this->supportedEngines = $supportedEngines;
        }

        if (null !== $supportedModels) {
            $this->supportedModels = $supportedModels;
        }

        if (null !== $usage) {
            $this->usage = $usage;
        }

        if (null !== $version) {
            $this->version = $version;
        }
    }

    public function version(): ?string
    {
        return $this->version;
    }

    /** Only a parsable pair can be ordered; an unknown or odd version is never reported as behind. */
    public function isBehind(?string $latestVersion): bool
    {
        $current = null === $this->version ? null : RunnerVersion::tryFromString($this->version);
        $latest = null === $latestVersion ? null : RunnerVersion::tryFromString($latestVersion);

        return null !== $current && null !== $latest && $current->isOlderThan($latest);
    }

    public function requestUpdate(\DateTimeImmutable $at): void
    {
        if ($this->isArchived()) {
            throw new RunnerAlreadyArchivedException();
        }

        $this->updateRequestedAt = $at;
    }

    public function updateRequestedAt(): ?\DateTimeImmutable
    {
        return $this->updateRequestedAt;
    }

    /** True once: the request is handed over to the runner heartbeating now and forgotten. */
    public function consumeUpdateRequest(): bool
    {
        if (null === $this->updateRequestedAt) {
            return false;
        }

        $this->updateRequestedAt = null;

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function usage(): ?array
    {
        return $this->usage;
    }

    /**
     * @return string[]|null
     */
    public function supportedEngines(): ?array
    {
        return $this->supportedEngines;
    }

    /**
     * @return string[]|null
     */
    public function supportedModels(): ?array
    {
        return $this->supportedModels;
    }

    public function apiKeyId(): ?string
    {
        return $this->apiKeyId;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * One-way: there is deliberately no counterpart that brings an archived runner back,
     * because archiving also retires its API key and a revoked key can never be restored.
     */
    public function archive(\DateTimeImmutable $at): void
    {
        if ($this->isArchived()) {
            throw new RunnerAlreadyArchivedException();
        }

        $this->archivedAt = $at;
        $this->status = RunnerStatusEnum::OFFLINE;
    }

    /**
     * The stored status only ever changes on heartbeat, so a runner that has simply
     * stopped heartbeating would otherwise be reported as idle/working indefinitely.
     * WORKING vs IDLE is not stored — it is derived from whether the caller found an
     * active (lease not expired) RunnerJob claimed by this runner's name, since job
     * claim/report never reaches into this aggregate (see class docblock).
     */
    public function effectiveStatus(\DateTimeImmutable $now, bool $hasClaimedJob): RunnerStatusEnum
    {
        if (RunnerStatusEnum::OFFLINE === $this->status) {
            return $this->status;
        }

        if (null === $this->lastSeenAt || $now->getTimestamp() - $this->lastSeenAt->getTimestamp() > self::STALE_AFTER_SECONDS) {
            return RunnerStatusEnum::OFFLINE;
        }

        return $hasClaimedJob ? RunnerStatusEnum::WORKING : RunnerStatusEnum::IDLE;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
