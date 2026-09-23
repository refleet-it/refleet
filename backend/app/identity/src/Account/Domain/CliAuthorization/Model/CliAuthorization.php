<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\CliAuthorization\Model;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\CliAuthorization\Enum\CliAuthorizationStatusEnum;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationExpiredException;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotPendingException;
use App\Identity\Account\Domain\CliAuthorization\ValueObject\CliAuthorizationId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One `refleet login` attempt, browser-approved (OAuth device-flow shape): the CLI
 * holds the device secret and polls with it, the browser sees only the user code in
 * the URL. Keeping the two apart means a URL glimpsed over someone's shoulder cannot
 * be used to collect the API key the approval mints.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cli_authorizations', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'uniq_cli_authorizations_user_code', columns: ['user_code'])]
#[ORM\UniqueConstraint(name: 'uniq_cli_authorizations_device_secret_hash', columns: ['device_secret_hash'])]
class CliAuthorization
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: CliAuthorizationStatusEnum::class)]
    private CliAuthorizationStatusEnum $status = CliAuthorizationStatusEnum::PENDING;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Account $account = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    private function __construct(
        CliAuthorizationId $id,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private string $userCode,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private string $deviceSecretHash,
        #[ORM\Column(type: Types::STRING, length: 100)]
        private string $runnerName,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
    ) {
        $this->id = $id->asString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(
        CliAuthorizationId $id,
        string $userCode,
        string $deviceSecretHash,
        string $runnerName,
        \DateTimeImmutable $expiresAt,
    ): self {
        return new self($id, $userCode, $deviceSecretHash, $runnerName, $expiresAt);
    }

    public function id(): CliAuthorizationId
    {
        return CliAuthorizationId::fromString($this->id);
    }

    public function userCode(): string
    {
        return $this->userCode;
    }

    public function deviceSecretHash(): string
    {
        return $this->deviceSecretHash;
    }

    public function runnerName(): string
    {
        return $this->runnerName;
    }

    public function status(): CliAuthorizationStatusEnum
    {
        return $this->status;
    }

    public function account(): ?Account
    {
        return $this->account;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function decidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return CliAuthorizationStatusEnum::PENDING === $this->status;
    }

    public function approve(Account $account): void
    {
        $this->assertDecidable();

        $this->status = CliAuthorizationStatusEnum::APPROVED;
        $this->account = $account;
        $this->decidedAt = new \DateTimeImmutable();
    }

    public function deny(): void
    {
        $this->assertDecidable();

        $this->status = CliAuthorizationStatusEnum::DENIED;
        $this->decidedAt = new \DateTimeImmutable();
    }

    private function assertDecidable(): void
    {
        if ($this->isExpired()) {
            throw new CliAuthorizationExpiredException();
        }

        if (!$this->isPending()) {
            throw new CliAuthorizationNotPendingException();
        }
    }
}
