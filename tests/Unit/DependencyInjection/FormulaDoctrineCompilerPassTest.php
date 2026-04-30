<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Unit\DependencyInjection;

use Cryonighter\FormulaDoctrineBundle\DependencyInjection\FormulaDoctrineCompilerPass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class FormulaDoctrineCompilerPassTest extends TestCase
{
    public function testItAttachesConfiguratorToDoctrineOrmConfigurationServices(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(
            'cryonighter.formula_doctrine.configurator',
            new Definition(stdClass::class),
        );

        $container->setDefinition(
            'doctrine.orm.default_configuration',
            new Definition(stdClass::class),
        );

        $container->setDefinition(
            'doctrine.orm.customer_configuration',
            new Definition(stdClass::class),
        );

        $compilerPass = new FormulaDoctrineCompilerPass();
        $compilerPass->process($container);

        self::assertSame(
            ['cryonighter.formula_doctrine.configurator', 'configure'],
            $this->normalizeConfigurator(
                $container->getDefinition('doctrine.orm.default_configuration')->getConfigurator(),
            ),
        );

        self::assertSame(
            ['cryonighter.formula_doctrine.configurator', 'configure'],
            $this->normalizeConfigurator(
                $container->getDefinition('doctrine.orm.customer_configuration')->getConfigurator(),
            ),
        );
    }

    public function testItDoesNothingWhenConfiguratorServiceIsMissing(): void
    {
        $container = new ContainerBuilder();

        $container->setDefinition(
            'doctrine.orm.default_configuration',
            new Definition(stdClass::class),
        );

        $compilerPass = new FormulaDoctrineCompilerPass();
        $compilerPass->process($container);

        self::assertNull(
            $container->getDefinition('doctrine.orm.default_configuration')->getConfigurator(),
        );
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function normalizeConfigurator(mixed $configurator): ?array
    {
        if ($configurator === null) {
            return null;
        }

        self::assertIsArray($configurator);
        self::assertCount(2, $configurator);
        self::assertIsObject($configurator[0]);
        self::assertSame('configure', $configurator[1]);

        return [
            (string) $configurator[0],
            $configurator[1],
        ];
    }
}
