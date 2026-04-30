<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Integration;

use Cryonighter\FormulaDoctrine\Metadata\FormulaMetadataRegistry;
use Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// Booting a Symfony kernel may register global error/exception handlers.
// Keep these integration tests isolated so PHPUnit's global-state checks
// are not affected by Symfony internals.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormulaDoctrineBundleIntegrationTest extends TestCase
{
    public function testKernelBootsWithFormulaDoctrineBundle(): void
    {
        $this->withBootedKernel(function (TestKernel $kernel): void {
            self::assertTrue($kernel->getContainer()->has('kernel'));
        });
    }

    public function testFormulaDoctrineMetadataRegistryServiceIsAvailable(): void
    {
        $this->withBootedKernel(function (TestKernel $kernel): void {
            $registry = $kernel
                ->getContainer()
                ->get('cryonighter.formula_doctrine.metadata_registry');

            self::assertInstanceOf(FormulaMetadataRegistry::class, $registry);
        });
    }

    /**
     * @param callable(TestKernel): void $callback
     */
    private function withBootedKernel(callable $callback): void
    {
        $kernel = new TestKernel('test', false);

        try {
            $kernel->boot();

            $callback($kernel);
        } finally {
            $kernel->shutdown();
        }
    }
}
