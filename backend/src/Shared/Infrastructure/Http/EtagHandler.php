<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\Cache\ResourceVersionCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EtagHandler
{
    public function __construct(private ResourceVersionCache $versionCache)
    {
    }

    public function handleNotModified(Request $request, Response $response, string $resourceType, string $id): bool
    {
        $version = $this->versionCache->get($resourceType, $id);
        if (null === $version) {
            return false;
        }

        $response->setEtag('"'.$version.'"');

        return $response->isNotModified($request);
    }

    public function setResourceEtag(Response $response, string $resourceType, string $id, int $version): void
    {
        $response->setEtag('"'.$version.'"');
        $this->versionCache->set($resourceType, $id, $version);
    }

    public function handleCollectionNotModified(Request $request, Response $response, string $collectionKey): bool
    {
        $version = $this->versionCache->getCollection($collectionKey);
        if (null === $version) {
            return false;
        }

        $response->setEtag('"'.$version.'"');

        return $response->isNotModified($request);
    }

    public function setCollectionEtag(Response $response, string $collectionKey): void
    {
        $version = $this->versionCache->getCollection($collectionKey);
        if (null === $version) {
            return;
        }

        $response->setEtag('"'.$version.'"');
    }
}
