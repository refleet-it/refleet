<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Carries messages between bounded contexts as `{type, data}` JSON rather than PHP class
 * names, so producer and consumer each keep their own copy of the message class and stay
 * free of any import across the boundary. Subclasses supply the type → class map used on
 * the receiving side; `type` itself is derived from the message's folder name.
 */
abstract readonly class MappedMessageSerializer implements SerializerInterface
{
    /**
     * Whitelist of allowed Symfony Messenger stamp classes for secure deserialization.
     * Only these stamp classes can be unserialized to prevent PHP object injection attacks.
     */
    private const array ALLOWED_STAMP_CLASSES = [
        BusNameStamp::class,
        DelayStamp::class,
        ErrorDetailsStamp::class,
        HandledStamp::class,
        ReceivedStamp::class,
        RedeliveryStamp::class,
        SentStamp::class,
        TransportMessageIdStamp::class,
        ValidationStamp::class,
    ];

    /**
     * What the stamps above carry inside: RedeliveryStamp holds the redelivery time and
     * ErrorDetailsStamp the flattened exception. unserialize() needs them named too, or a
     * retried message comes back with __PHP_Incomplete_Class in a typed property and dies.
     */
    private const array ALLOWED_STAMP_PAYLOAD_CLASSES = [
        \DateTimeImmutable::class,
        \DateTime::class,
        FlattenException::class,
    ];

    /**
     * @param array<string, class-string> $messageMap
     */
    public function __construct(
        private array $messageMap,
    ) {
    }

    #[\Override]
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        // Extract message type from class name (convention: folder name)
        $reflection = new \ReflectionClass($message);
        $namespace = $reflection->getNamespaceName();
        $parts = \explode('\\', $namespace);
        $folderName = \end($parts);
        $type = \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $folderName));

        $data = \get_object_vars($message);

        $encoded = \json_encode([
            'type' => $type,
            'data' => $data,
        ]);

        if (false === $encoded) {
            throw new \RuntimeException('Failed to encode message');
        }

        // Preserve envelope stamps for proper message handling — but only the ones decode()
        // will accept back. The worker adds transient stamps of its own on the way to a retry
        // (AckStamp carries a closure), and serializing those kills the consumer mid-retry.
        $stamps = [];
        foreach ($envelope->all() as $stampType => $stampInstances) {
            if (!\in_array($stampType, self::ALLOWED_STAMP_CLASSES, true)) {
                continue;
            }

            $stamps[$stampType] = \array_map(
                \serialize(...),
                $stampInstances
            );
        }

        return [
            'body' => $encoded,
            'headers' => [],
            'stamps' => $stamps,
        ];
    }

    #[\Override]
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'] ?? null;
        if (!\is_string($body)) {
            throw new \RuntimeException('Invalid body in encoded envelope');
        }

        $decoded = $this->decodeBody($body);
        $messageClass = $this->resolveMessageClass($decoded);
        $validatedData = $this->validateMessageData($decoded['data']);

        // Types are enforced by the message class' own constructor signature; anything that
        // does not fit surfaces as a TypeError rather than being silently accepted.
        $message = new $messageClass(...$validatedData);
        $stamps = $this->deserializeStamps($encodedEnvelope);

        return new Envelope($message, $stamps);
    }

    /**
     * @return array{type: string, data: array<mixed>}
     */
    private function decodeBody(string $body): array
    {
        $decoded = \json_decode($body, true);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('Invalid encoded envelope');
        }

        if (!isset($decoded['type']) || !\is_string($decoded['type'])) {
            throw new \RuntimeException('Message type not found in encoded envelope');
        }

        if (!isset($decoded['data']) || !\is_array($decoded['data'])) {
            throw new \RuntimeException('Message data not found in encoded envelope');
        }

        return ['type' => $decoded['type'], 'data' => $decoded['data']];
    }

    /**
     * @param array{type: string, data: array<mixed>} $decoded
     *
     * @return class-string
     */
    private function resolveMessageClass(array $decoded): string
    {
        $messageClass = $this->messageMap[$decoded['type']] ?? null;

        if (null === $messageClass) {
            throw new \RuntimeException('Unknown message type: '.$decoded['type']);
        }

        return $messageClass;
    }

    /**
     * Only guards that the payload is a named argument list. Values stay as json_decode
     * produced them (scalar, array or null) — it cannot yield objects, so there is nothing
     * here to inject.
     *
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    private function validateMessageData(array $data): array
    {
        $validatedData = [];
        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new \RuntimeException('Invalid key type in message data');
            }

            $validatedData[$key] = $value;
        }

        return $validatedData;
    }

    /**
     * @param array<string, mixed> $encodedEnvelope
     *
     * @return StampInterface[]
     */
    private function deserializeStamps(array $encodedEnvelope): array
    {
        if (!isset($encodedEnvelope['stamps']) || !\is_array($encodedEnvelope['stamps'])) {
            return [];
        }

        $stamps = [];
        foreach ($encodedEnvelope['stamps'] as $stampInstances) {
            if (\is_array($stampInstances)) {
                $stamps = \array_merge($stamps, $this->deserializeStampGroup($stampInstances));
            }
        }

        return $stamps;
    }

    /**
     * @param array<mixed> $stampInstances
     *
     * @return StampInterface[]
     */
    private function deserializeStampGroup(array $stampInstances): array
    {
        $stamps = [];
        foreach ($stampInstances as $serializedStamp) {
            if (!\is_string($serializedStamp)) {
                continue;
            }

            $stamp = \unserialize($serializedStamp, [
                'allowed_classes' => [...self::ALLOWED_STAMP_CLASSES, ...self::ALLOWED_STAMP_PAYLOAD_CLASSES],
            ]);
            if ($stamp instanceof StampInterface) {
                $stamps[] = $stamp;
            }
        }

        return $stamps;
    }
}
