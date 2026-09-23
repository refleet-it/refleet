<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\GetAvailableModels;

use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Unions supportedModels across every non-archived runner in the organization's fleet,
 * grouped by the engine(s) each runner declared — the closest honest substitute for
 * "ask the agent which models it supports": neither the claude CLI nor kiro-cli's ACP
 * implementation expose a live model-listing call, so this reflects what each runner
 * was configured to report on heartbeat instead of a fixed application-level list.
 */
#[AsMessageHandler]
final readonly class GetAvailableModelsHandler
{
    public function __construct(
        private RunnerRepositoryInterface $runners,
    ) {
    }

    public function __invoke(GetAvailableModelsQuery $query): AvailableModels
    {
        $organizationId = OrganizationId::fromString($query->organizationId);

        $claude = [];
        $kiro = [];

        foreach ($this->runners->findByOrganizationId($organizationId) as $runner) {
            if ($runner->isArchived()) {
                continue;
            }

            $models = $runner->supportedModels() ?? [];
            if ([] === $models) {
                continue;
            }

            foreach ($runner->supportedEngines() ?? [] as $engine) {
                if ('claude' === $engine) {
                    $claude = [...$claude, ...$models];
                } elseif ('kiro' === $engine) {
                    $kiro = [...$kiro, ...$models];
                }
            }
        }

        return new AvailableModels(
            claude: \array_values(\array_unique($claude)),
            kiro: \array_values(\array_unique($kiro)),
        );
    }
}
