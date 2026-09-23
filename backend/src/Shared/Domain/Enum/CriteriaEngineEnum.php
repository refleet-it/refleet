<?php

declare(strict_types=1);

namespace App\Shared\Domain\Enum;

/**
 * Which agent CLI runs a criteria's prompt (CLAUDE, KIRO) — null defaults to CLAUDE for
 * backward compatibility with criteria created before this distinction existed. A runner
 * fleet member only claims a job whose engine matches one it auto-detected on its host
 * (see runner/agent/), the same claim-time supportedEngines filter used by
 * ClaimRunnerJobRequest.
 */
enum CriteriaEngineEnum: string
{
    case CLAUDE = 'claude';
    case KIRO = 'kiro';
}
