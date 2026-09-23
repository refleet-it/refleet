<?php

declare(strict_types=1);

namespace App\Shared\Application\Query;

use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\CursorListResponse;

/**
 * @template T
 */
interface CursorListQueryInterface
{
    /**
     * @return CursorListResponse<T>
     */
    public function getList(CursorListParameters $parameters): CursorListResponse;

    /**
     * @return T[]
     */
    public function getAll(CursorListParameters $parameters): array;
}
