<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class AppUrlTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    #[\Override]
    public function getGlobals(): array
    {
        return [
            'app_url' => $this->appUrl,
        ];
    }
}
