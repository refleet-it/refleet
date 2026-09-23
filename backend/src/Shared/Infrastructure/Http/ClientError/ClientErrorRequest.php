<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ClientError;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ClientErrorRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Message is required')]
        #[Assert\Length(max: 2000)]
        public string $message,
        #[Assert\NotBlank(message: 'URL is required')]
        #[Assert\Length(max: 2000)]
        public string $url,
        #[Assert\Length(max: 8000)]
        public ?string $stack = null,
        #[Assert\Length(max: 500)]
        public ?string $componentStack = null,
    ) {
    }
}
