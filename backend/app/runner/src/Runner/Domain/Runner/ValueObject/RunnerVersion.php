<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\Runner\ValueObject;

/**
 * `<major>.<minor>.<patch>` as CI stamps it on every published runner (see
 * ci/jobs/quality-runner.yml). Compared numerically, so 0.1.9 < 0.1.186.
 */
final readonly class RunnerVersion
{
    private function __construct(
        private int $major,
        private int $minor,
        private int $patch,
    ) {
    }

    public static function tryFromString(string $version): ?self
    {
        if (1 !== \preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', \trim($version), $matches)) {
            return null;
        }

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    public function isOlderThan(self $other): bool
    {
        return [$this->major, $this->minor, $this->patch] < [$other->major, $other->minor, $other->patch];
    }
}
