<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Tests\Resources\App;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use ZJKiza\HttpResponseValidator\ZJKizaHttpResponseValidatorBundle;

final class ZJKizaHttpResponseValidatorBundleTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \realpath(__DIR__.'/..'); // @phpstan-ignore-line
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new ZJKizaHttpResponseValidatorBundle(),
        ];
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test'          => true,
            'property_info' => [
                'enabled' => true,
            ],
        ]);

        $container->import(__DIR__.'/config/services_test.yaml');
    }
}
