<?php

declare(strict_types=1);

namespace App\Tests\Helpers;

trait BackdatesTimestampsTrait
{
    /**
     * Sets a DateTimeImmutable property on an entity to 1 second in the past via Reflection,
     * removing the need for sleep(1) in timestamp-change assertions.
     */
    private function backdateProperty(object $entity, string $property, string $offset = '-1 second'): \DateTimeImmutable
    {
        $past = new \DateTimeImmutable($offset);
        (new \ReflectionProperty($entity, $property))->setValue($entity, $past);

        return $past;
    }
}
