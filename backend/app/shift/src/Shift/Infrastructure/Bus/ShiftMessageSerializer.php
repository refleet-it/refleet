<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Bus;

use App\Shared\Infrastructure\Messenger\MappedMessageSerializer;
use App\Shift\Shift\Infrastructure\Bus\ShiftTargetResultReported\ShiftTargetResultReportedMessage;

final readonly class ShiftMessageSerializer extends MappedMessageSerializer
{
    public function __construct()
    {
        parent::__construct([
            'shift_target_result_reported' => ShiftTargetResultReportedMessage::class,
        ]);
    }
}
