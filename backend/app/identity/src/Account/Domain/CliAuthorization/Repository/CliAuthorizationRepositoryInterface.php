<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\CliAuthorization\Repository;

use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;

interface CliAuthorizationRepositoryInterface
{
    public function save(CliAuthorization $authorization): void;

    public function delete(CliAuthorization $authorization): void;

    public function findByUserCode(string $userCode): ?CliAuthorization;

    public function findByDeviceSecretHash(string $deviceSecretHash): ?CliAuthorization;

    /** Nothing sweeps this table otherwise; callers run it opportunistically on each new request. */
    public function deleteExpiredBefore(\DateTimeImmutable $moment): void;
}
