<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Integration;

use Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\Entity\Customer;
use Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\Entity\CustomerOrder;
use Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FormulaDoctrineBundleSmokeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::ensureKernelShutdown();
    }

    public function testFormulaFieldIsPopulatedInSymfonyDoctrineIntegration(): void
    {
        self::bootKernel([
            'debug' => false,
        ]);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

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
