<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Enum;

enum GitLabSyncStatusEnum: string
{
    case NEVER_SYNCED = 'never_synced';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
