<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Bus;

use App\Qualification\Qualification\Infrastructure\Bus\QualificationTargetResultReported\QualificationTargetResultReportedMessage;
use App\Shared\Infrastructure\Messenger\MappedMessageSerializer;

final readonly class QualificationMessageSerializer extends MappedMessageSerializer
{
    public function __construct()
    {
        parent::__construct([
            'qualification_target_result_reported' => QualificationTargetResultReportedMessage::class,
        ]);
    }
}
