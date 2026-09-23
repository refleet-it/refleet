<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Shared\Infrastructure\Logger;

use Psr\Log\AbstractLogger;

final class InMemoryLogger extends AbstractLogger
{
    public array $records = [];

    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
