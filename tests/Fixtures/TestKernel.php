<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Fixtures;

use Cryonighter\FormulaDoctrineBundle\FormulaDoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private string $cacheDir;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        $token = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? (string) getmypid();

        $this->cacheDir = sys_get_temp_dir() . "/formula_doctrine_bundle/cache/$token/$this->environment";
    }

    public function shutdown(): void
    {
        parent::shutdown();

        (new Filesystem())->remove($this->cacheDir);
    }

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
        return $this->cacheDir;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/formula_doctrine_bundle/log/' . basename($this->cacheDir);
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
