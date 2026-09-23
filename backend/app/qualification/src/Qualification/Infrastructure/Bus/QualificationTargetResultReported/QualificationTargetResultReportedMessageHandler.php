<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Bus\QualificationTargetResultReported;

use App\Qualification\Qualification\Application\Command\RecordQualificationTargetResult\RecordQualificationTargetResultCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class QualificationTargetResultReportedMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(QualificationTargetResultReportedMessage $message): void
    {
        $this->bus->dispatch(new RecordQualificationTargetResultCommand(
            qualificationId: $message->qualificationId,
            targetId: $message->targetId,
            organizationId: $message->organizationId,
            success: $message->success,
            summary: $message->summary,
            runnerName: $message->runnerName,
            errorMessage: $message->errorMessage,
            score: $message->score,
        ));
    }
}
