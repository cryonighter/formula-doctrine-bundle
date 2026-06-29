# Formula Doctrine Bundle

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Total Downloads][ico-downloads]][link-downloads]

Symfony bundle for integrating [`cryonighter/formula-doctrine`](https://github.com/cryonighter/formula-doctrine)
into Symfony applications.

It enables Hibernate-style `#[Formula]` computed fields for Doctrine ORM entities
and wires the required Doctrine metadata listeners, SQL walker configuration and
DBAL middleware automatically through Symfony's dependency injection container.

Use it when you want read-only entity properties whose values are computed by DQL/SQL
expressions, subqueries, aggregations or joins — without adding physical database
columns and without introducing N+1 queries.

Example with native SQL subquery – **must be** enclosed in parentheses:

```php
#[ORM\Entity]
class Customer
{
    #[Formula('(SELECT COUNT(*) FROM orders o WHERE o.customer_id = {this}.id)')]
    public int $orderCount = 0;
}
```

Example using DQL subquery – **should not** be enclosed in parentheses:

```php
#[ORM\Entity]
class Customer
{
    #[Formula('SELECT COUNT(o) FROM App\Entity\Order o WHERE o.customer = {this}')]
    public int $orderCount = 0;
}
```

With this bundle installed, formula fields are populated automatically when entities
are loaded through Doctrine in a Symfony application. The bundle keeps your entity
code focused on the `#[Formula]` attributes while taking care of registering the
integration services needed by [`cryonighter/formula-doctrine`](https://github.com/cryonighter/formula-doctrine).


## Requirements

- **PHP >= 8.2.0** but the latest stable version of PHP is recommended

## Install

Via Composer

```shell script
composer require cryonighter/formula-doctrine-bundle
```

The bundle will be automatically registered in `config/bundles.php`:

```php
return [
    // ...
    Cryonighter\FormulaDoctrine\FormulaDoctrineBundle::class => ['all' => true],
];
```


### Bundle Registration Order

If you use other bundles that extend Doctrine ORM with custom SQL walkers
(e.g. Gedmo DoctrineExtensions, API Platform), register `FormulaDoctrineBundle`
**last** in `config/bundles.php`:
```
php
return [
    // ... other bundles ...
    Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle::class => ['all' => true],
    Cryonighter\FormulaDoctrine\FormulaDoctrineBundle::class => ['all' => true], // ← last
];
```
`FormulaDoctrineBundle` automatically detects and chains with any previously
registered output walker, so both transformations are applied to every query.

If another bundle is registered after `FormulaDoctrineBundle` and also sets a
custom output walker globally, you may need to manually call
`FormulaDoctrineConfigurator::configure()` in your application's bundle.


## Usage

### Basic example

Add `#[Formula]` to any property on a Doctrine entity.
The property **must not** be mapped with `#[ORM\Column]`.

```php
use Cryonighter\FormulaDoctrine\Attribute\Formula;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customers')]
class Customer
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    public int $id;

    #[ORM\Column]
    public string $name;

    // DQL — must NOT be enclosed in parentheses
    #[Formula('SELECT COUNT(o) FROM App\Entity\Order o WHERE o.customer = {this}')]
    public int $orderCount = 0;

    // Native SQL — must be enclosed in parentheses
    #[Formula('(SELECT COALESCE(SUM(oi.price), 0) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.customer_id = {this}.id)')]
    public float $totalRevenue = 0.0;

    // Nullable formula
    #[Formula('(SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = {this}.id)')]
    public ?string $lastOrderDate = null;
}
```


### Fetching entities

No changes to your query code are needed.
Formula fields are populated automatically on every DQL `SELECT`:

```php
$customers = $entityManager
    ->createQuery('SELECT c FROM App\Entity\Customer c')
    ->getResult();

foreach ($customers as $customer) {
    echo $customer->orderCount;    // populated from subquery
    echo $customer->totalRevenue;  // populated from subquery
}
```


A single SQL query is executed — no N+1:

```postgresql
SELECT c0_.id AS id_0,
       c0_.name AS name_1,
       (SELECT COUNT(o0_.id) AS sclr_1 FROM orders o0_ WHERE o0_.customer_id = c0_.id) AS orderCount_2,
       (SELECT COALESCE(SUM(oi.price), 0) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.customer_id = c0_.id) AS totalRevenue_3,
       (SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = c0_.id) AS lastOrderDate_4
FROM customers c0_
```


### QueryBuilder

Works with `QueryBuilder` too:

```php
$customers = $entityManager
    ->createQueryBuilder()
    ->select('c')
    ->from(Customer::class, 'c')
    ->where('c.name LIKE :name')
    ->setParameter('name', '%Acme%')
    ->getQuery()
    ->getResult();
```

And in the repositories too:

```php
class CustomerRepository extends ServiceEntityRepository
{
    public function findTopCustomers(int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        // $result[0]->totalRevenue is populated automatically
    }
}
```

Methods find(), findBy(), findOneBy() and findAll() are also supported:

```php
$customerRepository = $this->em->getRepository(Customer::class);

$customers = $customerRepository->findAll();

echo $customer[0]->orderCount;    // populated from subquery
echo $customer[0]->totalRevenue;  // populated from subquery
```

### Using formula fields in queries

Formula fields can be used in `WHERE`, `ORDER BY`, `GROUP BY` and `HAVING` clauses
just like regular entity properties:

#### WHERE clause

Filter entities by computed values:

```php
// DQL
$customers = $entityManager
    ->createQuery('SELECT c FROM App\Entity\Customer c WHERE c.orderCount > :minOrders')
    ->setParameter('minOrders', 5)
    ->getResult();

// QueryBuilder
$customers = $entityManager
    ->createQueryBuilder()
    ->select('c')
    ->from(Customer::class, 'c')
    ->where('c.totalRevenue >= :minRevenue')
    ->setParameter('minRevenue', 1000.0)
    ->getQuery()
    ->getResult();

// Repository findBy()
$customers = $customerRepository->findBy(['orderCount' => 10]);
```


#### ORDER BY clause

Sort by formula fields:

```php
// DQL
$customers = $entityManager
    ->createQuery('SELECT c FROM App\Entity\Customer c ORDER BY c.totalRevenue DESC')
    ->getResult();

// QueryBuilder
$customers = $entityManager
    ->createQueryBuilder()
    ->select('c')
    ->from(Customer::class, 'c')
    ->orderBy('c.orderCount', 'DESC')
    ->getQuery()
    ->getResult();

// Repository findBy() with ordering
$customers = $customerRepository->findBy(
    [],
    ['totalRevenue' => 'DESC']
);
```


#### GROUP BY and HAVING clauses

Aggregate and filter by computed values:

```php
// Group customers by order count and filter groups
$result = $entityManager
    ->createQuery('
        SELECT c.orderCount, COUNT(c.id) as customerCount, AVG(c.totalRevenue) as avgRevenue
        FROM App\Entity\Customer c
        GROUP BY c.orderCount
        HAVING c.orderCount >= :minOrders AND COUNT(c.id) > :minCustomers
        ORDER BY c.orderCount DESC
    ')
    ->setParameter('minOrders', 3)
    ->setParameter('minCustomers', 1)
    ->getResult();

// Result example:
// [
//   ['orderCount' => 10, 'customerCount' => 5, 'avgRevenue' => 15000.50],
//   ['orderCount' => 7,  'customerCount' => 3, 'avgRevenue' => 8500.25],
//   ...
// ]
```


#### Combined example

All clauses together in a single query:

```php
$result = $entityManager
    ->createQuery('
        SELECT c.orderCount, COUNT(c.id) as total
        FROM App\Entity\Customer c
        WHERE c.totalRevenue > :minRevenue
        GROUP BY c.orderCount
        HAVING c.orderCount BETWEEN :minOrders AND :maxOrders
        ORDER BY c.orderCount DESC
    ')
    ->setParameter('minRevenue', 500.0)
    ->setParameter('minOrders', 2)
    ->setParameter('maxOrders', 10)
    ->getResult();
```

> **Note:** Formula fields work transparently in all query clauses.
> The SQL subquery is embedded only once per query, not per clause usage.


### Aggregate functions

All DQL aggregate functions (e.g. `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`) work with formula fields out of the box:

```php
$result = $entityManager
    ->createQueryBuilder()
    ->select(
        'SUM(c.orderCount) as totalOrders',
        'AVG(c.totalRevenue) as avgRevenue',
        'MAX(c.totalRevenue) as maxRevenue',
        'MIN(c.totalRevenue) as minRevenue',
    )
    ->from(Customer::class, 'c')
    ->getQuery()
    ->getSingleResult();

// Result example:
// [
//   'totalOrders' => 42,
//   'avgRevenue'  => 1500.50,
//   'maxRevenue'  => 9800.00,
//   'minRevenue'  => 0.0,
// ]
```

> **Note:** `MIN` and `MAX` ignore `NULL` values — so nullable formula fields
> (e.g. `?float $maxOrderTotal`) behave correctly even when some entities
> have no related records.


### CASE WHEN expressions

Formula fields can be used inside `CASE WHEN ... THEN ... END` expressions
directly in DQL — for categorisation, conditional sorting and custom labels:

```php
// Categorise customers by revenue tier
$result = $entityManager
   ->createQuery('
        SELECT c.name, c.totalRevenue,
            CASE
                WHEN c.totalRevenue = 0    THEN \'none\'
                WHEN c.totalRevenue < 500  THEN \'low\'
                WHEN c.totalRevenue < 5000 THEN \'medium\'
                ELSE                            \'high\'
            END as revenueCategory
        FROM App\Entity\Customer c
        ORDER BY c.totalRevenue ASC
  ')
  ->getResult();

// Result example:
// [
//   ['name' => 'Alice', 'totalRevenue' => 0.0,    'revenueCategory' => 'none'],
//   ['name' => 'Bob',   'totalRevenue' => 320.0,  'revenueCategory' => 'low'],
//   ['name' => 'Carol', 'totalRevenue' => 1500.0, 'revenueCategory' => 'medium'],
//   ['name' => 'Dave',  'totalRevenue' => 9800.0, 'revenueCategory' => 'high'],
// ]

// CASE WHEN in ORDER BY — push inactive customers to the end
$result = $entityManager
    ->createQuery('
        SELECT c.name, c.orderCount
        FROM App\Entity\Customer c
        ORDER BY CASE WHEN c.orderCount = 0 THEN 1 ELSE 0 END ASC, c.orderCount DESC
    ')
    ->getResult();
```


### Nullable fields

If a formula can return `NULL` (e.g. `MAX` on an empty set),
declare the property as nullable — the type is inferred automatically:

```php
#[Formula('(SELECT MAX(o.total) FROM orders o WHERE o.customer_id = {this}.id)')]
public ?float $maxOrderTotal = null;
```


### The `{this}` placeholder

Use `{this}` to reference the root entity's table alias in the native SQL expression or root entity itself in the DQL expression.

In **native SQL**, `{this}` is resolved to the actual Doctrine-generated table alias (e.g. `c0_`):

```php
// {this} → c0_ (SQL table alias)
#[Formula('(SELECT COUNT(*) FROM orders o WHERE o.customer_id = {this}.id)')]
public int $orderCount = 0;
```

In **DQL**, `{this}` refers to the root entity itself, so you compare against the entity
reference directly — without a field suffix:

```php
// {this} → the root entity alias
#[Formula('SELECT COUNT(o) FROM App\Entity\Order o WHERE o.customer = {this}')]
public int $orderCount = 0;
```

> **Do not** hardcode the table name or alias directly — it will break when Doctrine
> generates a different alias.


### Custom SELECT alias

By default the SQL column alias matches the property name.
Override it with the `alias` parameter:

```php
#[Formula(
    sql: '(SELECT COUNT(*) FROM orders o WHERE o.customer_id = {this}.id)',
    alias: 'total_orders',
)]
public int $orderCount = 0;
```

> Use a custom alias only when you need to control the raw SQL column name,
> e.g. for compatibility with a specific reporting tool.


### Nested Formulas

A `#[Formula]` expression can reference a formula field of another entity.
The entire chain is resolved into a **single SQL query**.

```php
#[ORM\Entity]
class Customer
{
    #[ORM\ManyToOne(targetEntity: Country::class)]
    public Country $country;

    // DQL formula — exposed under alias 'orders'
    #[Formula('SELECT COUNT(o) FROM App\Entity\Order o WHERE o.customer = {this}', alias: 'orders')]
    public int $orderCount = 0;

    // SQL formula — exposed by property name 'totalRevenue'
    #[Formula('(SELECT COALESCE(SUM(oi.price), 0) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.customer_id = {this}.id)')]
    public float $totalRevenue = 0.0;
}

#[ORM\Entity]
class Country
{
    // SQL formula — references Customer.orderCount by its alias 'orders'
    #[Formula('(SELECT COALESCE(SUM(c.orders), 0) FROM customers c WHERE c.country_id = {this}.id)')]
    public int $customerOrderCount = 0;

    // DQL formula — references Customer.totalRevenue by property name
    #[Formula('SELECT COALESCE(SUM(c.totalRevenue), 0) FROM App\Entity\Customer c WHERE c.country = {this}')]
    public float $customerRevenue = 0.0;
}
```
> **Note:** In a **native SQL** expression, reference another formula field by
> its **`alias`** if one is declared (e.g. `c.total`), or by the property name otherwise.
> In a **DQL** expression, **always** use the property name (e.g. `c.orderCount`).


### UPDATE queries

Formula fields can be used in the `WHERE` clause of DQL `UPDATE` queries —
filter which entities to update based on computed values:

```php
// Update all customers who placed 10 or more orders
$affected = $entityManager
    ->createQuery('UPDATE App\Entity\Customer c SET c.name = :newName WHERE c.orderCount >= :min')
    ->setParameter('newName', 'VIP')
    ->setParameter('min', 10)
    ->execute();
```

> **Note:** Formula fields are read-only and are never written to the database.
> They can only appear in `WHERE` clauses of `UPDATE`/`DELETE` — not in the `SET` clause.


### DELETE queries

Formula fields work identically in DQL `DELETE` queries:

```php
// Delete customers who have never placed an order
$affected = $entityManager
    ->createQuery('DELETE App\Entity\Customer c WHERE c.orderCount = :count')
    ->setParameter('count', 0)
    ->execute();
```

## How it works

You can read about this in the description of the base package [`cryonighter/formula-doctrine`](https://github.com/cryonighter/formula-doctrine#how-it-works).

## Limitations

| Limitation             | Notes                                                                                                                                                                                                                                               |
|------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Read-only fields       | Formula fields must not have `#[ORM\Column]`. They are registered internally by the library and must never be written to the database.                                                                                                              |
| Scalar types only      | Supported PHP types: `int`, `float`, `string`, `bool`, `\DateTime`, `\DateTimeImmutable`, `\DateTimeInterface` and their nullable variants. Always provide a default value for non-nullable formula properties (e.g. `public int $orderCount = 0`). |
| Native SQL             | `$em->getConnection()->executeQuery(...)` bypasses both Walker and Middleware entirely — formula fields will hold their default PHP values.                                                                                                         |
| Schema Tool            | `doctrine:schema:create` and `doctrine:schema:update` do not create columns for formula fields — they have no physical column in the database. This is correct behaviour.                                                                           |
| Walker Chaining order  | `FormulaDoctrineBundle` must be registered **last** in `config/bundles.php` among Doctrine-extending bundles to ensure correct Walker Chaining. See [Bundle Registration Order](#bundle-registration-order).                                        |

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
# All tests
./vendor/bin/phpunit

# Only unit
./vendor/bin/phpunit --testsuite Unit

# Only integration
./vendor/bin/phpunit --testsuite Integration

# Specific file
./vendor/bin/phpunit tests/Unit/DependencyInjection/FormulaDoctrineCompilerPassTest.php

# With coating (requires Xdebug or PCOV)
./vendor/bin/phpunit --coverage-text
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email `cryonighter@yandex.ru` instead of using the issue tracker.

## Credits

- [Andrey Reshetchenko][link-author]

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/

[ico-version]: https://img.shields.io/packagist/v/cryonighter/formula-doctrine-bundle.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/cryonighter/formula-doctrine-bundle.svg?style=flat-square

[link-author]: https://github.com/cryonighter
[link-packagist]: https://packagist.org/packages/cryonighter/formula-doctrine-bundle
[link-downloads]: https://packagist.org/packages/cryonighter/formula-doctrine-bundle
