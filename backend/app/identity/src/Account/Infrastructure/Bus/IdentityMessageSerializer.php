<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus;

use App\Identity\Account\Infrastructure\Bus\RunnerArchived\RunnerArchivedMessage;
use App\Shared\Infrastructure\Messenger\MappedMessageSerializer;

final readonly class IdentityMessageSerializer extends MappedMessageSerializer
{
    public function __construct()
    {
        parent::__construct([
            'runner_archived' => RunnerArchivedMessage::class,
        ]);
    }
}
