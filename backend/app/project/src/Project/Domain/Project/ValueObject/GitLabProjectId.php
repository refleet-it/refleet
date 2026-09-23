<?php

declare(strict_types=1);

namespace App\Project\Project\Domain\Project\ValueObject;

use Webmozart\Assert\Assert;

final readonly class GitLabProjectId implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {
        Assert::stringNotEmpty($value, 'GitLab project ID must not be empty');
        Assert::digits($value, 'GitLab project ID must be numeric: '.$value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
