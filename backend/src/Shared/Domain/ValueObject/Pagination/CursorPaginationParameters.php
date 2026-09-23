<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Pagination;

use App\Shared\Domain\Exception\InvalidPaginationParametersException;

final readonly class CursorPaginationParameters
{
    private const int DEFAULT_LIMIT = 6;

    private const int MAX_LIMIT = 100;

    private const int MIN_LIMIT = 1;

    public function __construct(
        public ?string $cursor = null,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
        $this->validate();
    }

    public static function fromRequest(?string $cursor = null, ?int $limit = null): self
    {
        $decodedCursor = null;
        if (null !== $cursor && '' !== $cursor) {
            $decodedCursor = self::decodeCursor($cursor);
        }

        return new self(
            cursor: $decodedCursor,
            limit: $limit ?? self::DEFAULT_LIMIT,
        );
    }

    public function getCursor(): ?string
    {
        return $this->cursor;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function hasCursor(): bool
    {
        return null !== $this->cursor;
    }

    /**
     * @param array<mixed> $items
     */
    public function getNextCursor(array $items, string $idField = 'id'): ?string
    {
        if ([] === $items) {
            return null;
        }

        $lastItem = \end($items);
        if (!\is_array($lastItem) && !\is_object($lastItem)) {
            return null;
        }

        $id = $this->extractId($lastItem, $idField);
        $idString = $this->idToString($id);

        if (null === $idString) {
            return null;
        }

        return $this->encodeCursor($idString);
    }

    private function extractId(mixed $item, string $idField): mixed
    {
        if (\is_array($item)) {
            return $item[$idField] ?? null;
        }

        if (!\is_object($item)) {
            return null;
        }

        if (\method_exists($item, 'getId')) {
            return $item->getId();
        }

        if (\method_exists($item, 'id')) {
            return $item->id();
        }

        if (\property_exists($item, $idField)) {
            $property = new \ReflectionProperty($item, $idField);
            if ($property->isPublic()) {
                return $property->getValue($item);
            }
        }

        return null;
    }

    private function idToString(mixed $id): ?string
    {
        if ($id instanceof \Stringable) {
            return (string) $id;
        }

        if (\is_string($id)) {
            return $id;
        }

        if (\is_numeric($id)) {
            return (string) $id;
        }

        return null;
    }

    private function validate(): void
    {
        if ($this->limit < self::MIN_LIMIT || $this->limit > self::MAX_LIMIT) {
            throw new InvalidPaginationParametersException(\sprintf('Limit must be between %d and %d', self::MIN_LIMIT, self::MAX_LIMIT));
        }
    }

    private static function decodeCursor(string $encodedCursor): ?string
    {
        try {
            $decoded = \base64_decode($encodedCursor, true);
            if (false === $decoded) {
                return null;
            }

            $cursorData = \json_decode($decoded, true);
            if (!\is_array($cursorData) || !isset($cursorData['id']) || !\is_string($cursorData['id'])) {
                return null;
            }

            return $cursorData['id'];
        } catch (\Exception) {
            return null;
        }
    }

    private function encodeCursor(string $id): string
    {
        $cursorData = [
            'id' => $id,
            'timestamp' => \time(),
        ];

        $jsonString = \json_encode($cursorData);
        if (false === $jsonString) {
            throw new \RuntimeException('Failed to encode cursor data to JSON');
        }

        return \base64_encode($jsonString);
    }
}
