<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Bus;

use App\Notification\Notification\Infrastructure\Bus\EmailVerification\EmailVerificationMessage;
use App\Notification\Notification\Infrastructure\Bus\PasswordReset\PasswordResetMessage;
use App\Notification\Notification\Infrastructure\Bus\PasswordResetCompleted\PasswordResetCompletedMessage;
use App\Notification\Notification\Infrastructure\Bus\QualificationFinished\QualificationFinishedMessage;
use App\Notification\Notification\Infrastructure\Bus\ShiftFinished\ShiftFinishedMessage;
use App\Shared\Infrastructure\Messenger\MappedMessageSerializer;

final readonly class NotificationMessageSerializer extends MappedMessageSerializer
{
    public function __construct()
    {
        parent::__construct([
            'email_verification' => EmailVerificationMessage::class,
            'password_reset' => PasswordResetMessage::class,
            'password_reset_completed' => PasswordResetCompletedMessage::class,
            'shift_finished' => ShiftFinishedMessage::class,
            'qualification_finished' => QualificationFinishedMessage::class,
        ]);
    }
}
