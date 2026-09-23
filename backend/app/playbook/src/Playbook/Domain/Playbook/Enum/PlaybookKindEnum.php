<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Enum;

/**
 * What a playbook contributes to a prompt: a TASK is the change or criteria itself (one per
 * prompt, may take parameters and carry a preferred engine/model); a RULE is a standing
 * instruction that goes in front of it (any number, concatenated, optionally on by default).
 */
enum PlaybookKindEnum: string
{
    case TASK = 'task';
    case RULE = 'rule';
}
