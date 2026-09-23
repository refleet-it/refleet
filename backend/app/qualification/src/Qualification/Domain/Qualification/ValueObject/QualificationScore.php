<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\ValueObject;

use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationScoreException;

/**
 * The agent's verdict on one target, on a five-point scale — the product decision that
 * a project either clearly matches (4–5) or does not (1–3) lives here, not in the runner
 * and not in the prompt, so every agent is held to the same bar.
 */
final readonly class QualificationScore
{
    public const int MIN = 1;

    public const int MAX = 5;

    public const int QUALIFYING_THRESHOLD = 4;

    private function __construct(
        private int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidQualificationScoreException($value);
        }

        return new self($value);
    }

    public function qualifies(): bool
    {
        return $this->value >= self::QUALIFYING_THRESHOLD;
    }

    public function asInt(): int
    {
        return $this->value;
    }
}
