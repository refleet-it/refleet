<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\ValueObject;

/**
 * The label every merge request opened by a runner carries, so a customer can tell
 * Refleet's work apart from their own at a glance and filter by it.
 */
final readonly class GitLabLabel
{
    private const string REFLEET_NAME = 'refleet';

    private const string REFLEET_COLOR = '#000000';

    private const string REFLEET_DESCRIPTION = 'Merge requests opened by Refleet';

    private function __construct(
        public string $name,
        public string $color,
        public string $description,
    ) {
    }

    public static function refleet(): self
    {
        return new self(self::REFLEET_NAME, self::REFLEET_COLOR, self::REFLEET_DESCRIPTION);
    }

    public function matches(string $color, string $description): bool
    {
        return \strtolower($this->color) === \strtolower($color) && $this->description === $description;
    }
}
