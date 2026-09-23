<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Serializer;

use App\Shared\Domain\ValueObject\Id;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class IdNormalizer implements NormalizerInterface
{
    #[\Override]
    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        if (!$object instanceof Id) {
            throw new \InvalidArgumentException('Object must be an instance of Id');
        }

        return $object->asString();
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Id;
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            Id::class => true,
        ];
    }
}
