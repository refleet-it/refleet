<?php

declare(strict_types=1);

namespace App\File\File\Domain\ValueObject;

use App\File\File\Domain\Exception\InvalidMimeTypeException;
use App\File\File\Domain\Exception\MimeTypeCannotBeEmptyException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(Response::HTTP_BAD_REQUEST)]
final readonly class MimeType implements \Stringable
{
    private const array ALLOWED_TYPES = [
        // Obrazy
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        // Dokumenty
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // Tekst
        'text/plain',
        'text/csv',
        // Archiwa
        'application/zip',
        'application/x-rar-compressed',
        // Audio
        'audio/mpeg',
        'audio/wav',
        // Video
        'video/mp4',
        'video/x-msvideo',
    ];

    private function __construct(
        private string $value,
    ) {
        if (\in_array(\trim($this->value), ['', '0'], true)) {
            throw new MimeTypeCannotBeEmptyException();
        }

        if (!\in_array($this->value, self::ALLOWED_TYPES, true)) {
            throw new InvalidMimeTypeException($this->value);
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * @return array<string>
     */
    public static function getAllowedTypes(): array
    {
        return self::ALLOWED_TYPES;
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

    public function isImage(): bool
    {
        return \str_starts_with($this->value, 'image/');
    }

    public function isDocument(): bool
    {
        return \str_starts_with($this->value, 'application/') && !$this->isArchive();
    }

    public function isArchive(): bool
    {
        return \in_array($this->value, ['application/zip', 'application/x-rar-compressed'], true);
    }

    public function isText(): bool
    {
        return \str_starts_with($this->value, 'text/');
    }
}
