<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        getConfigDir as private getSharedConfigDir;
    }

    /**
     * Registers every context at once. Contexts are being extracted into their own
     * application ids one at a time, so this stays the default until the last one has
     * moved — see docs/adr/0001-multiple-kernels.md.
     */
    public const string DEFAULT_APP_ID = 'monolith';

    private readonly string $id;

    public function __construct(string $environment, bool $debug, ?string $id = null)
    {
        $this->id = $id ?? $this->readEnv('APP_ID') ?? self::DEFAULT_APP_ID;

        parent::__construct($environment, $debug);
    }

    public function getAppId(): string
    {
        return $this->id;
    }

    public function getAppConfigDir(): string
    {
        return $this->getProjectDir().'/app/'.$this->id.'/config';
    }

    #[\Override]
    public function getCacheDir(): string
    {
        $base = $this->readEnv('APP_CACHE_DIR') ?? $this->getProjectDir().'/var/cache';

        return $base.'/'.$this->id.'/'.$this->environment;
    }

    #[\Override]
    public function getLogDir(): string
    {
        $base = $this->readEnv('APP_LOG_DIR') ?? $this->getProjectDir().'/var/log';

        return $base.'/'.$this->id;
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        $bundles = $this->readBundles($this->getSharedConfigDir().'/bundles.php');

        $appBundles = $this->getAppConfigDir().'/bundles.php';
        if (\is_file($appBundles)) {
            $bundles = \array_merge($bundles, $this->readBundles($appBundles));
        }

        foreach ($bundles as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $this->importContainerConfig($container, $this->getSharedConfigDir());
        $this->importContainerConfig($container, $this->getAppConfigDir());
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->importRoutingConfig($routes, $this->getSharedConfigDir());
        $this->importRoutingConfig($routes, $this->getAppConfigDir());
    }

    private function readEnv(string $key): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @return array<class-string<BundleInterface>, array<string, bool>>
     */
    private function readBundles(string $file): array
    {
        /** @var array<class-string<BundleInterface>, array<string, bool>> $bundles */
        $bundles = require $file;

        return $bundles;
    }

    private function importContainerConfig(ContainerConfigurator $container, string $configDir): void
    {
        $container->import($configDir.'/{packages}/*.{php,yaml}');
        $container->import($configDir.'/{packages}/'.$this->environment.'/*.{php,yaml}');

        if (\is_file($configDir.'/services.yaml')) {
            $container->import($configDir.'/services.yaml');
            $container->import($configDir.'/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import($configDir.'/{services}.php');
        }
    }

    private function importRoutingConfig(RoutingConfigurator $routes, string $configDir): void
    {
        $routes->import($configDir.'/{routes}/'.$this->environment.'/*.{php,yaml}');
        $routes->import($configDir.'/{routes}/*.{php,yaml}');

        if (\is_file($configDir.'/routes.yaml')) {
            $routes->import($configDir.'/routes.yaml');
        } else {
            $routes->import($configDir.'/{routes}.php');
        }
    }
}
