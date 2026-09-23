<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\HasStatus;
use App\Shared\Domain\ValueObject\Status;

final class HasStatusTestSubject
{
    use HasStatus;

    public function __construct(?Status $status = null)
    {
        if (null !== $status) {
            $this->setStatus($status);
        }
    }

    public function setStatusPublic(Status $status): void
    {
        $this->setStatus($status);
    }

    public function activatePublic(): void
    {
        $this->activate();
    }

    public function deletePublic(): void
    {
        $this->delete();
    }
}
