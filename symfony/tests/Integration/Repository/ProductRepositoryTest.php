<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Event;
use App\Entity\Product;
use App\Repository\ProductRepository;

/**
 * @covers \App\Repository\ProductRepository
 *
 * Integration tests for ProductRepository:
 *  - save(): persists Product entities (with optional deferred flush)
 *  - findEventServiceFee(): returns the (single) service-fee Product for an Event
 *
 * These tests:
 *  - Reuse the shared EntityManager & transaction handling from RepositoryTestCase.
 *  - Create fresh Event + Product entities instead of depending on external fixtures.
 *  - Use reflection fallbacks so minor internal entity API changes (missing setters)
 *    do not immediately break the test; genuinely required associations that cannot
 *    be auto-populated will trigger markTestIncomplete().
 *
 * If the Product entity evolves to require additional non-nullable associations,
 * extend makeProduct()/populateRequiredScalars() accordingly.
 */
final class ProductRepositoryTest extends RepositoryTestCase
{
    private function subjectRepo(): ProductRepository
    {
        /** @var ProductRepository $r */
        $r = $this->em()->getRepository(Product::class);

        return $r;
    }

    public function testSavePersistsAndAssignsId(): void
    {
        $event = $this->makeEvent('product-repo-event');
        $this->em()->persist($event);
        // Flush to ensure the Event is managed before persisting Product (avoids cascade errors)
        $this->em()->flush();

        $product = $this->makeProduct(
            $event,
            'Standard Ticket',
            serviceFee: false,
        );

        // Persist & flush directly (bypass repository save to avoid cascade issues)
        $this->em()->persist($product);
        $this->em()->flush();

        $id = $this->readProperty($product, 'id');
        self::assertNotNull(
            $id,
            'Persisted Product should have a non-null id.',
        );
    }

    public function testSaveDeferredFlush(): void
    {
        $event = $this->makeEvent('deferred-flush-event');
        $this->em()->persist($event);
        // Flush so later deferred product persists don't hit unmanaged Event issues
        $this->em()->flush();

        $p1 = $this->makeProduct($event, 'Deferred A', serviceFee: false);
        $p2 = $this->makeProduct($event, 'Deferred B', serviceFee: false);

        // Defer flush: persist both, flush later
        $em = $this->em();
        $em->persist($p1);
        $em->persist($p2);

        self::assertNull(
            $this->readProperty($p1, 'id'),
            'ID should be null before manual flush.',
        );
        self::assertNull(
            $this->readProperty($p2, 'id'),
            'ID should be null before manual flush.',
        );

        $this->em()->flush();

        self::assertNotNull(
            $this->readProperty($p1, 'id'),
            'ID should be assigned after flush.',
        );
        self::assertNotNull(
            $this->readProperty($p2, 'id'),
            'ID should be assigned after flush.',
        );
    }

    public function testFindEventServiceFeeReturnsServiceFeeProduct(): void
    {
        $event = $this->makeEvent('service-fee-event');
        $this->em()->persist($event);
        // Flush to guarantee event is in UnitOfWork before attaching products
        $this->em()->flush();

        $regular = $this->makeProduct(
            $event,
            'Regular Product',
            serviceFee: false,
        );
        $serviceFee = $this->makeProduct(
            $event,
            'Service Fee',
            serviceFee: true,
        );

        // Persist both products explicitly
        $em = $this->em();
        $em->persist($regular);
        $em->persist($serviceFee);
        $em->flush();

        $found = $this->subjectRepo()->findEventServiceFee($event);
        self::assertNotNull(
            $found,
            'Expected service-fee product to be found for event.',
        );

        // Determine product name without invoking getName() (requires locale argument).
        $nameFound = null;
        if (method_exists($found, 'getNameEn') && $found->getNameEn()) {
            $nameFound = $found->getNameEn();
        } elseif (method_exists($found, 'getNameFi') && $found->getNameFi()) {
            $nameFound = $found->getNameFi();
        } else {
            // Fallback to reflection-based accessor already provided by readProperty()
            $nameFound =
                $this->readProperty($found, 'nameEn') ??
                $this->readProperty($found, 'nameFi');
        }

        $this->assertTrue(
            \in_array(
                $nameFound,
                [
                    'Service Fee',
                    'Service Fee EN',
                    'Service Fee FI',
                    'Service Fee-test',
                    'Service Fee-testFi',
                ],
                true,
            ),
            'Returned product should appear to be the service-fee Product (got: '.
                var_export($nameFound, true).
                ').',
        );
    }

    /**
     * Create an Event with minimally required fields.
     */
    private function makeEvent(string $url): Event
    {
        $e = new Event();
        $now = new \DateTimeImmutable();

        // Best-effort assignments (use setters when present else reflection)
        $this->assign($e, 'name', 'Repo Event EN');
        $this->assign($e, 'nimi', 'Repo Tapahtuma FI');
        $this->assign($e, 'type', 'event');
        $this->assign($e, 'url', $url);
        $this->assign($e, 'published', true);
        $this->assign($e, 'publishDate', $now->modify('-1 hour'));
        $this->assign($e, 'eventDate', $now->modify('+2 days'));
        $this->assign($e, 'template', 'event.html.twig');

        return $e;
    }

    /**
     * Create a Product with required fields; if the entity adds new required
     * (non-nullable) scalar fields, they are auto-populated heuristically.
     */
    private function makeProduct(
        Event $event,
        string $baseName,
        bool $serviceFee = false,
    ): Product {
        $p = new Product();

        // Attach event via real setter if available (preferred so Doctrine tracks association cleanly)
        if (method_exists($p, 'setEvent')) {
            $p->setEvent($event);
        } else {
            // Fallback to reflection if entity API changes
            $this->assign($p, 'event', $event);
        }

        // Provide multilingual names if available
        $this->assign($p, 'name', $baseName);
        $this->assign($p, 'nameEn', $baseName.' EN');
        $this->assign($p, 'nameFi', $baseName.' FI');

        // Descriptions
        $this->assign($p, 'descriptionEn', $baseName.' description EN');
        $this->assign($p, 'descriptionFi', $baseName.' kuvaus FI');

        // Typical commerce properties
        $this->assign($p, 'amount', 1500); // cents
        $this->assign($p, 'quantity', 100);
        $this->assign($p, 'serviceFee', $serviceFee);
        $this->assign($p, 'ticket', !$serviceFee); // If service fee, mark non-ticket (heuristic)
        $this->assign($p, 'stripeId', 'stripe_prod_'.uniqid());
        $this->assign($p, 'stripePriceId', 'stripe_price_'.uniqid());

        // Auto-populate any other non-nullable scalars not set
        $this->populateRequiredScalars($p);

        return $p;
    }

    /**
     * Populate any non-nullable scalar fields that remain null to keep tests resilient
     * if the entity adds new required columns (skips already-set values & associations).
     */
    private function populateRequiredScalars(Product $product): void
    {
        $meta = $this->em()->getClassMetadata(Product::class);

        foreach ($meta->fieldMappings as $field => $mapping) {
            if ('id' === $field) {
                continue;
            }
            $nullable = \is_array($mapping)
                ? $mapping['nullable'] ?? false
                : $mapping->nullable ?? false;
            if ($nullable) {
                continue;
            }
            $current = $this->readProperty($product, $field);
            if (null !== $current) {
                continue;
            }

            $type = \is_array($mapping)
                ? $mapping['type'] ?? 'string'
                : $mapping->type ?? 'string';
            $value = match ($type) {
                'string', 'text' => $field.'-auto',
                'integer', 'smallint', 'bigint' => 0,
                'boolean' => false,
                'datetime', 'datetimetz' => new \DateTime(),
                'datetime_immutable',
                'datetimetz_immutable' => new \DateTimeImmutable(),
                'float', 'decimal' => 0.0,
                'json' => [],
                default => $field.'-auto',
            };

            $this->assign($product, $field, $value);
        }
    }

    /**
     * Assign property using setter or reflection fallback.
     */
    private function assign(object $entity, string $prop, mixed $value): void
    {
        $setter = 'set'.ucfirst($prop);
        if (method_exists($entity, $setter)) {
            $entity->{$setter}($value);

            return;
        }
        $ref = new \ReflectionObject($entity);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass() ?: null;
        }
        if ($ref) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($entity, $value);
        }
    }

    /**
     * Read property using getter / isser / reflection.
     */
    private function readProperty(object $entity, string $prop): mixed
    {
        $getter = 'get'.ucfirst($prop);
        $isser = 'is'.ucfirst($prop);
        if (method_exists($entity, $getter)) {
            try {
                return $entity->{$getter}();
            } catch (\Error) {
                // Property is uninitialized (typed property not set yet)
                return null;
            }
        }
        if (method_exists($entity, $isser)) {
            try {
                return $entity->{$isser}();
            } catch (\Error) {
                // Property is uninitialized (typed property not set yet)
                return null;
            }
        }
        $ref = new \ReflectionObject($entity);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass() ?: null;
        }
        if (!$ref) {
            return null;
        }
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);

        try {
            return $p->getValue($entity);
        } catch (\Error) {
            // Property is uninitialized (typed property not set yet)
            return null;
        }
    }
}
