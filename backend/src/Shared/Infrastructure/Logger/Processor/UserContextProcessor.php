<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logger\Processor;

use App\Shared\Domain\User\AccountUser;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Adds authenticated user context to log records.
 * Adds: user_id, email, roles.
 */
final readonly class UserContextProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $user = $this->security->getUser();

        if (!$user instanceof AccountUser) {
            return $record;
        }

        return $record->with(
            context: \array_merge($record->context, [
                'user_id' => $user->getUserId()->asString(),
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ])
        );
    }
}
