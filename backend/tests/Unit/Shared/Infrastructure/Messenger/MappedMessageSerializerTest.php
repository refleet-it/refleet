<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Messenger\MappedMessageSerializer;
use App\Shared\Infrastructure\Messenger\OutboundMessageSerializer;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Producer and consumer each keep their own copy of a message class, and nothing but discipline
 * keeps the copies in step: the wire type is derived from the folder name, and the payload is
 * spread into the consumer's constructor as named arguments. Rename a field on one side only and
 * every message of that kind dies on arrival — the job that produced it simply never finishes.
 * These cases hold the contract that the copies agree, across whatever messages exist today.
 */
#[CoversClass(MappedMessageSerializer::class)]
final class MappedMessageSerializerTest extends TestCase
{
    /**
     * @param list<string> $copies
     */
    #[Test]
    #[DataProvider('crossContextMessages')]
    public function every_copy_of_a_message_lives_in_an_identically_named_folder(string $shortName, array $copies): void
    {
        // Arrange
        $folders = [];
        foreach ($copies as $file) {
            $folders[$file] = \basename(\dirname($file));
        }

        // Assert
        Assert::assertCount(
            1,
            \array_unique($folders),
            \sprintf('%s is carried under more than one wire type: %s', $shortName, \json_encode($folders))
        );
    }

    /**
     * @param list<string> $copies
     */
    #[Test]
    #[DataProvider('crossContextMessages')]
    public function every_copy_of_a_message_declares_the_same_constructor_parameters(string $shortName, array $copies): void
    {
        // Arrange
        $signatures = [];
        foreach ($copies as $file) {
            $signatures[$file] = $this->signatureOf($this->classIn($file));
        }

        // Act
        $first = \array_key_first($signatures);

        // Assert
        foreach ($signatures as $file => $signature) {
            Assert::assertSame(
                $signatures[$first],
                $signature,
                \sprintf('%s disagrees between %s and %s', $shortName, \basename(\dirname((string) $first, 6)), \basename(\dirname($file, 6)))
            );
        }
    }

    // A message can only be decoded by the transport it is routed to, and only if that transport's
    // serializer knows its wire type. Registering the class but forgetting the map entry is silent
    // until the first message is actually sent.
    #[Test]
    #[DataProvider('routedMessages')]
    public function every_routed_message_has_its_wire_type_registered_in_the_receiving_serializer(
        string $messageClass,
        string $wireType,
        string $serializerClass,
    ): void {
        // Arrange
        $map = $this->messageMapOf($serializerClass);

        // Assert
        Assert::assertArrayHasKey(
            $wireType,
            $map,
            \sprintf('%s is routed to %s, which cannot decode "%s"', $messageClass, $serializerClass, $wireType)
        );
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function crossContextMessages(): iterable
    {
        $groups = [];
        foreach (self::messageFiles() as $file) {
            $groups[\basename($file, '.php')][] = $file;
        }

        foreach ($groups as $shortName => $copies) {
            if (\count($copies) > 1) {
                yield $shortName => [$shortName, $copies];
            }
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function routedMessages(): iterable
    {
        // Each context declares its own transports/routing in app/<context>/config/packages/messenger.yaml
        // (config/packages/messenger.yaml only holds the bus skeleton, shared across all — see
        // that file's own comment for why). A transport a context only produces to is declared
        // there too, but with the generic, map-less OutboundMessageSerializer; the transport's
        // one real, decode-capable serializer lives in whichever file's context actually
        // consumes it, so it wins over any generic declaration when files disagree.
        $serializers = [];
        $routing = [];

        foreach (self::messengerConfigFiles() as $file) {
            $config = Yaml::parseFile($file)['framework']['messenger'] ?? [];

            foreach ($config['transports'] ?? [] as $name => $transport) {
                if (!\is_array($transport) || !isset($transport['serializer'])) {
                    continue;
                }

                $isGeneric = OutboundMessageSerializer::class === $transport['serializer'];
                if (!isset($serializers[$name]) || !$isGeneric) {
                    $serializers[$name] = $transport['serializer'];
                }
            }

            foreach ($config['routing'] ?? [] as $messageClass => $transport) {
                $routing[$messageClass] = $transport;
            }
        }

        foreach ($routing as $messageClass => $transport) {
            if (isset($serializers[$transport])) {
                yield $messageClass => [$messageClass, self::wireTypeOf($messageClass), $serializers[$transport]];
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function messengerConfigFiles(): array
    {
        $files = \glob(self::backendDir().'/app/*/config/packages/messenger.yaml') ?: [];
        \sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private static function messageFiles(): array
    {
        $files = \glob(self::backendDir().'/app/*/src/*/Infrastructure/Bus/*/*Message.php') ?: [];
        \sort($files);

        return $files;
    }

    private static function backendDir(): string
    {
        return \dirname(__DIR__, 5);
    }

    private function classIn(string $file): string
    {
        $source = (string) \file_get_contents($file);

        if (1 !== \preg_match('/^namespace\s+([^;]+);/m', $source, $matches)) {
            throw new \RuntimeException('No namespace declared in '.$file);
        }

        return \trim($matches[1]).'\\'.\basename($file, '.php');
    }

    /**
     * Named arguments make declaration order irrelevant, so the signature is compared as a
     * name => type map rather than a list.
     *
     * @return array<string, string>
     */
    private function signatureOf(string $class): array
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();

        if (null === $constructor) {
            return [];
        }

        $signature = [];
        foreach ($constructor->getParameters() as $parameter) {
            $signature[$parameter->getName()] = (string) $parameter->getType();
        }

        \ksort($signature);

        return $signature;
    }

    /**
     * Mirrors the derivation in MappedMessageSerializer::encode(): the folder holding the message
     * class is what travels on the wire.
     */
    private static function wireTypeOf(string $class): string
    {
        $parts = \explode('\\', $class);
        $folder = $parts[\count($parts) - 2];

        return \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $folder));
    }

    /**
     * @return array<string, class-string>
     */
    private function messageMapOf(string $serializerClass): array
    {
        $property = (new \ReflectionClass(MappedMessageSerializer::class))->getProperty('messageMap');

        /** @var array<string, class-string> $map */
        $map = $property->getValue(new $serializerClass());

        return $map;
    }
}
