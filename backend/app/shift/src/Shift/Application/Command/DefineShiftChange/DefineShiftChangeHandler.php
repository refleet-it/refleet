<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\DefineShiftChange;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\PromptSource;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DefineShiftChangeHandler
{
    public function __construct(
        private ShiftRepositoryInterface $shifts,
    ) {
    }

    public function __invoke(DefineShiftChangeCommand $command): void
    {
        $shift = $this->findOwnedShift($command->shiftId, $command->organizationId);

        $engine = null !== $command->changeEngine ? CriteriaEngineEnum::from($command->changeEngine) : null;

        $criteria = match (CriteriaModeEnum::from($command->changeMode)) {
            CriteriaModeEnum::AI => ChangeCriteria::ai(
                $command->changePrompt ?? '',
                $command->changeModel,
                $engine,
                $command->changeRules,
                \array_map(PromptSource::fromArray(...), $command->changeSources),
            ),
        };

        $shift->defineChange($criteria);

        $this->shifts->save($shift);
    }

    private function findOwnedShift(string $shiftId, string $organizationId): Shift
    {
        $shift = $this->shifts->findByIdForOrganization(ShiftId::fromString($shiftId), OrganizationId::fromString($organizationId));

        if (null === $shift) {
            throw new ShiftNotFoundException();
        }

        return $shift;
    }
}
