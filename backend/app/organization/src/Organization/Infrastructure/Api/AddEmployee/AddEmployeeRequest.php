<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\AddEmployee;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'AddEmployee',
    description: 'Payload used to add an already-registered account as an employee of the organization'
)]
final readonly class AddEmployeeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        #[OA\Property(type: 'string', format: 'email', example: 'employee@example.com')]
        public string $email,
    ) {
    }
}
