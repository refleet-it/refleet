<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\RunnerDirectoryInterface;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * RunnerDirectoryInterface adapter for every context but Runner, which implements it
 * directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpRunnerDirectory implements RunnerDirectoryInterface
{
    private string $runnerInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['RUNNER_INTERNAL_URL'] ?? throw new \InvalidArgumentException('RUNNER_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('RUNNER_INTERNAL_URL must be a non-empty string');
        }

        $this->runnerInternalUrl = $url;
    }

    #[\Override]
    public function idsByNameForOrganization(string $organizationId): array
    {
        /** @var array{idsByName: array<string, string>} $data */
        $data = $this->client->get($this->runnerInternalUrl, '/internal/runner-ids/'.$organizationId);

        return $data['idsByName'];
    }
}
