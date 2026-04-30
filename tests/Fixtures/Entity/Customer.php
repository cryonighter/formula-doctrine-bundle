<?php

namespace Cryonighter\FormulaDoctrineBundle\Tests\Fixtures\Entity;

use Cryonighter\FormulaDoctrine\Attribute\Formula;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customers')]
class Customer
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column]
    public string $name;

    #[Formula('(SELECT COUNT(*) FROM customer_orders co WHERE co.customer_id = {this}.id)')]
    public int $orderCount = 0;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
