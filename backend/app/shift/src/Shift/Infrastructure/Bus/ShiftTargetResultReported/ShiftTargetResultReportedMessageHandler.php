<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Bus\ShiftTargetResultReported;

use App\Shift\Shift\Application\Command\RecordShiftTargetResult\RecordShiftTargetResultCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ShiftTargetResultReportedMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ShiftTargetResultReportedMessage $message): void
    {
        $this->bus->dispatch(new RecordShiftTargetResultCommand(
            shiftId: $message->shiftId,
            targetId: $message->targetId,
            organizationId: $message->organizationId,
            success: $message->success,
            summary: $message->summary,
            runnerName: $message->runnerName,
            errorMessage: $message->errorMessage,
            branchName: $message->branchName,
            mergeRequestUrl: $message->mergeRequestUrl,
            mergeRequestIid: $message->mergeRequestIid,
        ));
    }
}
