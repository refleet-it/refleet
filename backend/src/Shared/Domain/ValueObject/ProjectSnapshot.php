<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Webmozart\Assert\Assert;

/**
 * A point-in-time copy of the project's identifying details, captured once when a
 * QualificationTarget/ShiftTarget is created so the runner never needs to query the
 * Project context on its hot path (claim/report).
 */
final readonly class ProjectSnapshot
{
    public function __construct(
        private string $externalId,
        private string $path,
        private string $name,
        private ?string $defaultBranch,
    ) {
        Assert::stringNotEmpty($externalId, 'Project snapshot external ID must not be empty');
        Assert::stringNotEmpty($path, 'Project snapshot path must not be empty');
        Assert::stringNotEmpty($name, 'Project snapshot name must not be empty');
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function defaultBranch(): ?string
    {
        return $this->defaultBranch;
    }
}
