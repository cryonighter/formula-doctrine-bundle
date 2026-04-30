<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Fixtures;

use Cryonighter\FormulaDoctrineBundle\FormulaDoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new FormulaDoctrineBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/formula_doctrine_bundle/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/formula_doctrine_bundle/log/' . $this->environment;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'formula-doctrine-bundle-test-secret',
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'path' => '%kernel.cache_dir%/test.db',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'auto_mapping' => false,
                'mappings' => [
                    'FormulaDoctrineBundleTests' => [
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }
}
