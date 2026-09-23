<?php

declare(strict_types=1);

namespace App\Shared\Application\Cache;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class ResourceVersionCache
{
    private const int TTL = 86400;

    public function __construct(private CacheInterface $cache)
    {
    }

    public function set(string $resourceType, string $id, int $version): void
    {
        $key = $this->resourceKey($resourceType, $id);
        $this->cache->delete($key);
        $this->cache->get($key, static function (ItemInterface $item) use ($version): int {
            $item->expiresAfter(self::TTL);

            return $version;
        });
    }

    public function get(string $resourceType, string $id): ?int
    {
        $key = $this->resourceKey($resourceType, $id);
        $value = $this->cache->get($key, static fn (): int => -1);

        return -1 === $value ? null : $value;
    }

    public function delete(string $resourceType, string $id): void
    {
        $this->cache->delete($this->resourceKey($resourceType, $id));
    }

    public function incrementCollection(string $collectionKey): void
    {
        $key = $this->collectionKey($collectionKey);
        $current = $this->cache->get($key, static function (ItemInterface $item): int {
            $item->expiresAfter(self::TTL);

            return 0;
        });
        $this->cache->delete($key);
        $this->cache->get($key, static function (ItemInterface $item) use ($current): int {
            $item->expiresAfter(self::TTL);

            return $current + 1;
        });
    }

    public function getCollection(string $collectionKey): ?int
    {
        $key = $this->collectionKey($collectionKey);
        $value = $this->cache->get($key, static fn (): int => -1);

        return -1 === $value ? null : $value;
    }

    private function resourceKey(string $resourceType, string $id): string
    {
        return \sprintf('etag_%s_%s', $resourceType, \str_replace('-', '_', $id));
    }

    private function collectionKey(string $collectionKey): string
    {
        return \sprintf('etag_coll_%s', \str_replace(['.', '-'], '_', $collectionKey));
    }
}
