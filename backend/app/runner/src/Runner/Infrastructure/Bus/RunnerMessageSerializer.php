<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Bus;

use App\Runner\Runner\Infrastructure\Bus\OwnerJobsCancellationRequested\OwnerJobsCancellationRequestedMessage;
use App\Runner\Runner\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use App\Shared\Infrastructure\Messenger\MappedMessageSerializer;

final readonly class RunnerMessageSerializer extends MappedMessageSerializer
{
    public function __construct()
    {
        parent::__construct([
            'runner_job_requested' => RunnerJobRequestedMessage::class,
            'owner_jobs_cancellation_requested' => OwnerJobsCancellationRequestedMessage::class,
        ]);
    }
}
