<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\Runner\Service;

/**
 * The newest runner version published for the fleet to run — null when that is not
 * known right now (the registry is unreachable, or the lookup is switched off), which
 * must never block a heartbeat.
 */
interface LatestRunnerVersionProviderInterface
{
    public function latestVersion(): ?string;
}
