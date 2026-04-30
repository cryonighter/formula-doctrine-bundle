<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_orders')]
class CustomerOrder
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(name: 'customer_id')]
    public int $customerId;

    #[ORM\Column]
    public int $amount;

    public function __construct(int $customerId, int $amount)
    {
        $this->customerId = $customerId;
        $this->amount = $amount;
    }
}
