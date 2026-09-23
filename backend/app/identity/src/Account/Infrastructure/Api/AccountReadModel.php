<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;

#[OA\Schema(
    schema: 'AccountReadModel',
    title: 'Account Read Model',
    description: 'Account data returned in lists and details'
)]
final readonly class AccountReadModel
{
    public function __construct(
        #[Groups(['account:list', 'account:detail', 'account:admin'])]
        #[OA\Property(description: 'Unique account identifier', type: 'string', format: 'uuid')]
        public string $id,
        #[Groups(['account:list', 'account:detail', 'account:admin'])]
        #[OA\Property(description: 'Account email address', type: 'string', format: 'email')]
        public string $email,
        #[Groups(['account:list', 'account:detail', 'account:admin'])]
        #[OA\Property(description: 'User role', type: 'string', enum: ['user', 'administrator'])]
        public string $role,
        #[Groups(['account:list', 'account:detail', 'account:admin'])]
        #[OA\Property(description: 'Account status', type: 'string', enum: ['active', 'inactive'])]
        public string $status,
        #[Groups(['account:detail', 'account:admin'])]
        #[OA\Property(description: 'Account creation timestamp', type: 'string', format: 'date-time')]
        public string $createdAt,
        #[Groups(['account:detail', 'account:admin'])]
        #[OA\Property(description: 'Last account update timestamp', type: 'string', format: 'date-time')]
        public string $updatedAt,
    ) {
    }
}
