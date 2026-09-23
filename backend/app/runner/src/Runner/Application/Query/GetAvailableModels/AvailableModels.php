<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\GetAvailableModels;

final readonly class AvailableModels
{
    /**
     * @param string[] $claude
     * @param string[] $kiro
     */
    public function __construct(
        public array $claude = [],
        public array $kiro = [],
    ) {
    }
}
