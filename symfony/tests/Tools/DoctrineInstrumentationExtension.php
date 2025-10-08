<?php

declare(strict_types=1);

namespace App\Tests\Tools;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension as ExtensionInterface;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * DoctrineInstrumentationExtension (PHPUnit 12 compatible).
 *
 * Registers a Finished test subscriber that:
 *  - Detects uninitialized Doctrine proxies left in the identity map after each test
 *  - Warns (or optionally fails) if the EntityManager is closed
 *  - Optionally logs entity "growth" (net new managed instances per class since previous test)
 *
 * Environment flags (set before invoking phpunit):
 *   FAIL_ON_UNINITIALIZED_PROXIES=1   Fail the test run if any uninitialized proxies remain
 *   FAIL_ON_CLOSED_ENTITY_MANAGER=1   Fail immediately if the EntityManager is closed after a test
 *   LOG_ENTITY_GROWTH=1               Log per-test increases in identity map population
 *
 * Add to phpunit.dist.xml:
 *
 *   <extensions>
 *       <extension class="App\Tests\Tools\DoctrineInstrumentationExtension"/>
 *   </extensions>
 *
 * (The class is autoloadable via dev autoload PSR-4 config.)
 */
final class DoctrineInstrumentationExtension implements ExtensionInterface
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class implements FinishedSubscriber {
            private ?EntityManagerInterface $em = null;

            private bool $failOnUninitialized;
            private bool $failOnClosed;
            private bool $logGrowth;

            /** @var array<class-string,int> */
            private array $lastCounts = [];

            private bool $initialized = false;

            public function __construct()
            {
                $this->failOnUninitialized = (bool) getenv('FAIL_ON_UNINITIALIZED_PROXIES');
                $this->failOnClosed = (bool) getenv('FAIL_ON_CLOSED_ENTITY_MANAGER');
                $this->logGrowth = (bool) getenv('LOG_ENTITY_GROWTH');
            }

            public function notify(Finished $event): void
            {
                $em = $this->entityManager();
                if (!$em) {
                    // Kernel not booted (e.g. pure unit test)
                    return;
                }

                if (!$this->initialized) {
                    $this->snapshot($em);
                    $this->initialized = true;
                }

                // Check closed EM
                if (!$em->isOpen()) {
                    $msg = '[DoctrineInstrumentation] EntityManager CLOSED after test '.$event->test()->id();
                    $this->stderr($msg);
                    if ($this->failOnClosed) {
                        throw new \RuntimeException($msg);
                    }
                    // Attempt reset so later tests can still proceed
                    $this->tryResetEntityManager();

                    return;
                }

                // Collect uninitialized proxies
                $uninitialized = $this->collectUninitialized($em);
                if ($uninitialized) {
                    $list = implode(', ', array_unique($uninitialized));
                    $msg = '[DoctrineInstrumentation] Uninitialized proxies after '.$event->test()->id().': '.$list;
                    $this->stderr($msg);
                    if ($this->failOnUninitialized) {
                        throw new \RuntimeException('Uninitialized Doctrine proxies detected: '.$list);
                    }
                }

                // Growth logging
                if ($this->logGrowth) {
                    $growth = $this->growthSinceLast($em);
                    if ($growth) {
                        $parts = [];
                        foreach ($growth as $class => $delta) {
                            $parts[] = $this->short($class).':+'.$delta;
                        }
                        $this->stderr(
                            '[DoctrineInstrumentation] Growth '.
                            $event->test()->id().
                            ' => '.implode(', ', $parts)
                        );
                    }
                }

                // Snapshot for next test
                $this->snapshot($em);
            }

            /* ---------------- Internal Helpers ---------------- */

            private function entityManager(): ?EntityManagerInterface
            {
                if ($this->em instanceof EntityManagerInterface) {
                    return $this->em;
                }
                if (!class_exists(\Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::class)) {
                    return null;
                }
                try {
                    $container = \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::getContainer();
                    if ($container->has('doctrine')) {
                        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
                        $registry = $container->get('doctrine');
                        $em = $registry->getManager();
                        if ($em instanceof EntityManagerInterface) {
                            $this->em = $em;

                            return $this->em;
                        }
                    }
                } catch (\Throwable) {
                    return null;
                }

                return null;
            }

            /**
             * @return list<class-string>
             */
            private function collectUninitialized(EntityManagerInterface $em): array
            {
                $uow = $em->getUnitOfWork();
                $map = $uow->getIdentityMap();
                $out = [];
                foreach ($map as $class => $entities) {
                    foreach ($entities as $entity) {
                        if ($entity instanceof Proxy && !$entity->__isInitialized()) {
                            /* @var class-string $class */
                            $out[] = $class;
                        }
                    }
                }

                return $out;
            }

            private function snapshot(EntityManagerInterface $em): void
            {
                $uow = $em->getUnitOfWork();
                $map = $uow->getIdentityMap();
                $counts = [];
                foreach ($map as $class => $entities) {
                    $counts[$class] = \count($entities);
                }
                $this->lastCounts = $counts;
            }

            /**
             * @return array<class-string,int> positive deltas only
             */
            private function growthSinceLast(EntityManagerInterface $em): array
            {
                $uow = $em->getUnitOfWork();
                $map = $uow->getIdentityMap();
                $current = [];
                foreach ($map as $class => $entities) {
                    $current[$class] = \count($entities);
                }
                $growth = [];
                foreach ($current as $class => $count) {
                    $prev = $this->lastCounts[$class] ?? 0;
                    $delta = $count - $prev;
                    if ($delta > 0) {
                        $growth[$class] = $delta;
                    }
                }

                return $growth;
            }

            private function tryResetEntityManager(): void
            {
                try {
                    $container = \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::getContainer();
                    if ($container->has('doctrine')) {
                        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
                        $registry = $container->get('doctrine');
                        $new = $registry->resetManager();
                        if ($new instanceof EntityManagerInterface) {
                            $this->em = $new;
                            $this->stderr('[DoctrineInstrumentation] EntityManager reset after closure.');
                        }
                    }
                } catch (\Throwable $e) {
                    $this->stderr('[DoctrineInstrumentation] Failed to reset EntityManager: '.$e->getMessage());
                }
            }

            private function short(string $fqcn): string
            {
                $p = strrpos($fqcn, '\\');

                return false === $p ? $fqcn : substr($fqcn, $p + 1);
            }

            private function stderr(string $msg): void
            {
                fwrite(STDERR, $msg.PHP_EOL);
            }
        });
    }
}
