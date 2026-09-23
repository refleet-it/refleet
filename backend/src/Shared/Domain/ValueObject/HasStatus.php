<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

trait HasStatus
{
    private Status $status;

    public function status(): Status
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return Status::ACTIVE === $this->status;
    }

    public function isDeleted(): bool
    {
        return Status::DELETED === $this->status;
    }

    protected function setStatus(Status $status): void
    {
        $this->status = $status;
    }

    protected function activate(): void
    {
        $this->status = Status::ACTIVE;
    }

    protected function delete(): void
    {
        $this->status = Status::DELETED;
    }
}
