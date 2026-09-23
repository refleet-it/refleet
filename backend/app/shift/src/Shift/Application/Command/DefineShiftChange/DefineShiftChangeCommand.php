<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\DefineShiftChange;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class DefineShiftChangeCommand implements CommandInterface
{
    /**
     * @param list<array{id: string, name: string, kind: string, builtIn?: bool}> $changeSources
     */
    public function __construct(
        public string $shiftId,
        public string $organizationId,
        public string $changeMode,
        public ?string $changeEngine = null,
        public ?string $changePrompt = null,
        public ?string $changeModel = null,
        public ?string $changeRules = null,
        public array $changeSources = [],
    ) {
    }
}
