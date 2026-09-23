<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\File;

use App\File\File\Domain\Model\File;
use App\File\File\Domain\ValueObject\FileId;
use App\File\File\Domain\ValueObject\FileName;
use App\File\File\Domain\ValueObject\FileSize;
use App\File\File\Domain\ValueObject\MimeType;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\TenantId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class FileFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return File::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => FileId::generate(),
            'name' => FileName::fromString('image.jpg'),
            'originalName' => FileName::fromString('image-original.jpg'),
            'mimeType' => MimeType::fromString('image/jpeg'),
            'size' => FileSize::fromBytes(1024),
            'path' => 'uploads/source/image.jpg',
            'description' => null,
            'uploaderId' => UserId::generate(),
            'tenantId' => TenantId::fromString('11111111-1111-1111-1111-111111111111'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
