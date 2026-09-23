<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Encodes messages for a transport this application only produces to, never consumes from.
 * Encoding needs no type map — see MappedMessageSerializer::encode(), whose logic this
 * mirrors — because the `{type, data}` envelope is derived purely by reflecting on the
 * outgoing message. Decoding is what needs the receiving context's own type map, so it stays
 * out of reach here on purpose: this class runs in every producing context's container, none
 * of which should be able to turn wire bytes back into another context's message class.
 */
final readonly class OutboundMessageSerializer implements SerializerInterface
{
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

        $stamps = [];
        foreach ($envelope->all() as $stampType => $stampInstances) {
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
        throw new \LogicException('OutboundMessageSerializer only encodes — this application does not consume from this transport.');
    }
}
