<?php

declare(strict_types=1);

namespace App\File\File\Domain\ValueObject;

use App\File\File\Domain\Exception\FileSizeCannotBeNegativeException;
use App\File\File\Domain\Exception\FileSizeTooLargeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(Response::HTTP_BAD_REQUEST)]
final readonly class FileSize
{
    private const int KILOBYTE = 1024;

    private const int MEGABYTE = 1024 * 1024;

    private const int MAX_SIZE_BYTES = 10 * self::MEGABYTE; // 10MB

    private function __construct(
        private int $value,
    ) {
        if ($this->value < 0) {
            throw new FileSizeCannotBeNegativeException();
        }

        if ($this->value > self::MAX_SIZE_BYTES) {
            throw new FileSizeTooLargeException($this->value, self::MAX_SIZE_BYTES);
        }
    }

    public static function fromBytes(int $bytes): self
    {
        return new self($bytes);
    }

    public static function getMaxSizeBytes(): int
    {
        return self::MAX_SIZE_BYTES;
    }

    public static function getMaxSizeFormatted(): string
    {
        return (new self(self::MAX_SIZE_BYTES))->getFormattedSize();
    }

    public function getFormattedSize(): string
    {
        if ($this->value < self::KILOBYTE) {
            return $this->value.' B';
        }

        if ($this->value < self::MEGABYTE) {
            return \round($this->value / self::KILOBYTE, 2).' KB';
        }

        return \round($this->value / self::MEGABYTE, 2).' MB';
    }

    public function asBytes(): int
    {
        return $this->value;
    }

    public function asKilobytes(): float
    {
        return $this->value / self::KILOBYTE;
    }

    public function asMegabytes(): float
    {
        return $this->value / self::MEGABYTE;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isLargerThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isSmallerThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function asInt(): int
    {
        return $this->value;
    }
}
