<?php

declare(strict_types=1);

namespace App\File\File\Domain\ValueObject;

use App\File\File\Domain\Exception\FileNameCannotBeEmptyException;
use App\File\File\Domain\Exception\FileNameTooLongException;
use App\File\File\Domain\Exception\InvalidFileNameException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(Response::HTTP_BAD_REQUEST)]
final readonly class FileName implements \Stringable
{
    public const int MAX_LENGTH = 255;

    /** @var array<string> */
    private const array EMPTY_SENTINELS = ['', '0'];

    private const string VALID_FILENAME_PATTERN = '/^[^<>:"\/\\|?*\x00-\x1f]+$/';

    private function __construct(
        private string $value,
    ) {
        if (\in_array(\trim($this->value), self::EMPTY_SENTINELS, true)) {
            throw new FileNameCannotBeEmptyException();
        }

        if (\strlen($this->value) > self::MAX_LENGTH) {
            throw new FileNameTooLongException();
        }

        if (1 !== \preg_match(self::VALID_FILENAME_PATTERN, $this->value)) {
            throw new InvalidFileNameException();
        }
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

    public function getExtension(): string
    {
        $parts = \explode('.', $this->value);

        return \count($parts) > 1 ? \end($parts) : '';
    }

    public function getNameWithoutExtension(): string
    {
        $parts = \explode('.', $this->value);

        return \count($parts) > 1 ? \implode('.', \array_slice($parts, 0, -1)) : $this->value;
    }
}
