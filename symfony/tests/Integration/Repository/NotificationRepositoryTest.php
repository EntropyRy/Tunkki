<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Notification;
use App\Repository\NotificationRepository;

/**
 * @covers \App\Repository\NotificationRepository
 *
 * Integration tests for the lightweight NotificationRepository. Since the
 * repository only wraps persist/remove (save/remove) we focus on verifying:
 *  - A Notification entity can be created and saved (flush=true).
 *  - The saved entity receives an identifier.
 *  - The remove() method deletes the entity (flush=true).
 *
 * We do not rely on prior fixtures for Notification records; instead we create
 * entities on demand and (if necessary) populate required scalar fields using
 * Doctrine metadata, similar to the strategy used in other repository tests.
 *
 * If the Notification entity introduces a required (non-nullable) association
 * without a readily available fixture strategy, the test will be marked
 * incomplete with a diagnostic message so it can be adapted safely without
 * producing false failures in CI.
 */
final class NotificationRepositoryTest extends RepositoryTestCase
{
    private function subjectRepo(): NotificationRepository
    {
        /** @var NotificationRepository $r */
        $r = $this->em()->getRepository(Notification::class);

        return $r;
    }

    public function testSavePersistsNotification(): void
    {
        $notification = $this->makeNotification();
        $this->subjectRepo()->save($notification, true);

        // Assert an ID was assigned (either getId()/id or reflection)
        $id = $this->readProperty($notification, 'id');
        self::assertNotNull(
            $id,
            'Expected saved Notification to have a non-null id.',
        );
    }

    public function testRemoveDeletesNotification(): void
    {
        $notification = $this->makeNotification();
        $this->subjectRepo()->save($notification, true);
        $id = $this->readProperty($notification, 'id');
        self::assertNotNull(
            $id,
            'Precondition: notification must have id after save.',
        );

        $this->subjectRepo()->remove($notification, true);

        $found = $this->subjectRepo()->find($id);
        self::assertNull(
            $found,
            'Notification should not be found after repository remove().',
        );
    }

    /**
     * Create a Notification entity and populate required scalar fields.
     */
    private function makeNotification(): Notification
    {
        $n = new Notification();

        // Populate required scalar fields (non-nullable) heuristically.
        $this->populateRequiredScalars($n);

        return $n;
    }

    /**
     * Populate required scalar (non-nullable) fields that are currently null.
     * If a required association is encountered with no strategy, mark incomplete.
     */
    private function populateRequiredScalars(Notification $entity): void
    {
        $em = $this->em();
        $meta = $em->getClassMetadata(Notification::class);

        // Handle scalar fields
        foreach ($meta->fieldMappings as $field => $mapping) {
            if ('id' === $field) {
                continue;
            }
            // Skip nullable or already set fields (support array or object style mappings)
            $nullable = \is_array($mapping)
                ? $mapping['nullable'] ?? false
                : $mapping->nullable ?? false;
            if ($nullable) {
                continue;
            }

            $current = $this->readProperty($entity, $field);
            if (null !== $current) {
                continue;
            }

            $type = \is_array($mapping)
                ? $mapping['type'] ?? 'string'
                : $mapping->type ?? 'string';
            $value = match ($type) {
                'string', 'text' => $field.'-test',
                'integer', 'smallint', 'bigint' => 0,
                'boolean' => false,
                'datetime', 'datetimetz' => new \DateTime(),
                'datetime_immutable',
                'datetimetz_immutable' => new \DateTimeImmutable(),
                'float', 'decimal' => 0.0,
                'json' => [],
                default => $field.'-test',
            };

            $this->assign($entity, $field, $value);
        }

        // Detect required associations (joinColumns nullable=false) without array-access deprecations
        foreach ($meta->associationMappings as $assoc => $am) {
            $joinColumns = null;
            if (\is_array($am)) {
                $joinColumns = $am['joinColumns'] ?? null;
            } elseif (isset($am->joinColumns)) {
                $joinColumns = $am->joinColumns;
            }

            $nullable = true;
            if (is_iterable($joinColumns)) {
                foreach ($joinColumns as $jc) {
                    $jcNullable = true;
                    if (\is_array($jc)) {
                        $jcNullable = $jc['nullable'] ?? true;
                    } elseif (
                        \is_object($jc)
                        && property_exists($jc, 'nullable')
                    ) {
                        $jcNullable = $jc->nullable ?? true;
                    }
                    if (false === $jcNullable) {
                        $nullable = false;
                        break;
                    }
                }
            }

            if (false === $nullable) {
                $current = $this->readProperty($entity, $assoc);
                if (null === $current) {
                    $this->markTestIncomplete(
                        \sprintf(
                            'Notification has a required association "%s" with no auto-fixture assignment strategy. Extend the test to supply it.',
                            $assoc,
                        ),
                    );
                }
            }
        }
    }

    /**
     * Assign a property using a conventional setter or reflection fallback.
     */
    private function assign(object $obj, string $prop, mixed $value): void
    {
        $setter = 'set'.ucfirst($prop);
        if (method_exists($obj, $setter)) {
            $obj->{$setter}($value);

            return;
        }

        $ref = new \ReflectionObject($obj);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass() ?: null;
        }
        if (!$ref) {
            // Silently ignore if the property truly does not exist (entity evolution)
            return;
        }

        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    }

    /**
     * Read a property via getter/isser or reflection fallback.
     */
    private function readProperty(object $obj, string $prop): mixed
    {
        $getter = 'get'.ucfirst($prop);
        $isser = 'is'.ucfirst($prop);
        if (method_exists($obj, $getter)) {
            return $obj->{$getter}();
        }
        if (method_exists($obj, $isser)) {
            return $obj->{$isser}();
        }

        $ref = new \ReflectionObject($obj);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass() ?: null;
        }
        if (!$ref) {
            return null;
        }

        $p = $ref->getProperty($prop);
        $p->setAccessible(true);

        return $p->getValue($obj);
    }
}
