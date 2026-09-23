<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Shared\Application\Query;

use App\Shared\Domain\ValueObject\Id;

final readonly class CursorListItemStub
{
    private Id $id;

    public function __construct(
        public string $content,
    ) {
        $this->id = Id::generate();
    }

    public function id(): Id
    {
        return $this->id;
    }
}
