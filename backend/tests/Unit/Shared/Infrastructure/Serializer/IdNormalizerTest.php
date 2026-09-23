<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Serializer;

use App\Shared\Domain\ValueObject\Id;
use App\Shared\Infrastructure\Serializer\IdNormalizer;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdNormalizer::class)]
final class IdNormalizerTest extends TestCase
{
    #[Test]
    public function normalize_returns_string_representation_of_id(): void
    {
        // Arrange
        $normalizer = new IdNormalizer();
        $id = Id::fromString('11111111-1111-1111-1111-111111111111');

        // Act
        $result = $normalizer->normalize($id);

        // Assert
        Assert::assertSame('11111111-1111-1111-1111-111111111111', $result);
    }

    #[Test]
    public function normalize_throws_when_object_is_not_id(): void
    {
        // Arrange
        $normalizer = new IdNormalizer();
        $object = new \stdClass();

        // Act
        try {
            $normalizer->normalize($object);
            Assert::fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $invalidArgumentException) {
            // Assert
            Assert::assertSame('Object must be an instance of Id', $invalidArgumentException->getMessage());
        }
    }

    #[Test]
    public function supports_normalization_accepts_id_only(): void
    {
        // Arrange
        $normalizer = new IdNormalizer();
        $id = Id::fromString('22222222-2222-2222-2222-222222222222');
        $other = '22222222-2222-2222-2222-222222222222';

        // Act
        $supportsId = $normalizer->supportsNormalization($id);
        $supportsOther = $normalizer->supportsNormalization($other);

        // Assert
        Assert::assertTrue($supportsId);
        Assert::assertFalse($supportsOther);
    }

    #[Test]
    public function get_supported_types_marks_id_as_cacheable(): void
    {
        // Arrange
        $normalizer = new IdNormalizer();

        // Act
        $supportedTypes = $normalizer->getSupportedTypes(null);

        // Assert
        Assert::assertSame([Id::class => true], $supportedTypes);
    }
}
