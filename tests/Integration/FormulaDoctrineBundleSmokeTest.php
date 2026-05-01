<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Integration;

use Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\Entity\Customer;
use Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\Entity\CustomerOrder;
use Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// Booting a Symfony kernel may register global error/exception handlers.
// Keep these integration tests isolated so PHPUnit's global-state checks
// are not affected by Symfony internals.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormulaDoctrineBundleSmokeTest extends TestCase
{
    public function testFormulaFieldIsPopulatedInSymfonyDoctrineIntegration(): void
    {
        $this->withBootedKernel(function (TestKernel $kernel): void {
            // Getting the entityManager by public alias
            $entityManager = $kernel->getContainer()->get('doctrine.orm.default_entity_manager');

            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

            $this->recreateSchema($entityManager);

            $customer = new Customer('Acme');

            $entityManager->persist($customer);
            $entityManager->flush();

            self::assertNotNull($customer->id);

            $entityManager->persist(new CustomerOrder($customer->id, 100));
            $entityManager->persist(new CustomerOrder($customer->id, 200));
            $entityManager->flush();
            $entityManager->clear();

            $loadedCustomer = $entityManager
                ->getRepository(Customer::class)
                ->find($customer->id);

            self::assertInstanceOf(Customer::class, $loadedCustomer);
            self::assertSame(2, $loadedCustomer->orderCount);
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

    private function recreateSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = [
            $entityManager->getClassMetadata(Customer::class),
            $entityManager->getClassMetadata(CustomerOrder::class),
        ];

        $schemaTool = new SchemaTool($entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
