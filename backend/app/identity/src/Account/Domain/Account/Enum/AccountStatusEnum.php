<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Enum;

enum AccountStatusEnum: string
{
    public function canLogin(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isPending(): bool
    {
        return self::PENDING === $this;
    }

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isSuspended(): bool
    {
        return self::SUSPENDED === $this;
    }

    public function isDeactivated(): bool
    {
        return self::DEACTIVATED === $this;
    }

    public function isPendingEmailVerification(): bool
    {
        return self::PENDING_EMAIL_VERIFICATION === $this;
    }

    case PENDING = 'pending';
    case PENDING_EMAIL_VERIFICATION = 'pending_email_verification';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DEACTIVATED = 'deactivated';
}
