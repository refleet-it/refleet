<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Bus;

use App\Organization\Organization\Infrastructure\Bus\AccountEmailChanged\AccountEmailChangedMessage;
use App\Organization\Organization\Infrastructure\Bus\AccountMirrored\AccountMirroredMessage;
use App\Shared\Infrastructure\Messenger\MappedMessageSerializer;

final readonly class OrganizationMessageSerializer extends MappedMessageSerializer
{
    public function __construct()
    {
        parent::__construct([
            'account_mirrored' => AccountMirroredMessage::class,
            'account_email_changed' => AccountEmailChangedMessage::class,
        ]);
    }
}
