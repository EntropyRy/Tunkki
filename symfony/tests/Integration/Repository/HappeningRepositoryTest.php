<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Happening;
use App\Factory\EventFactory;
use App\Factory\HappeningFactory;
use App\Repository\HappeningRepository;
use PHPUnit\Framework\Attributes\Group;
use Zenstruck\Foundry\Persistence\Proxy;

#[Group('repository')]
#[Group('happening')]
final class HappeningRepositoryTest extends RepositoryTestCase
{
    public function testFindPreviousAndNextReturnsNullsForSingleItem(): void
    {
        $event = EventFactory::new()->published()->create();
        $only = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 10:00:00'))
            ->create();
        $onlyEntity = $only instanceof Proxy ? $only->_real() : $only;

        $repo = $this->repo(Happening::class);
        \assert($repo instanceof HappeningRepository);

        $result = $repo->findPreviousAndNext($onlyEntity);

        self::assertSame([null, null], $result);
    }

    public function testFindPreviousAndNextHandlesFirstAndLast(): void
    {
        $event = EventFactory::new()->published()->create();
        $first = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 10:00:00'))
            ->create();
        $last = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 12:00:00'))
            ->create();

        $firstEntity = $first instanceof Proxy ? $first->_real() : $first;
        $lastEntity = $last instanceof Proxy ? $last->_real() : $last;

        $repo = $this->repo(Happening::class);
        \assert($repo instanceof HappeningRepository);

        $firstResult = $repo->findPreviousAndNext($firstEntity);
        $lastResult = $repo->findPreviousAndNext($lastEntity);

        self::assertSame([null, $lastEntity], $firstResult);
        self::assertSame([$firstEntity, null], $lastResult);
    }

    public function testFindPreviousAndNextReturnsMiddleNeighbors(): void
    {
        $event = EventFactory::new()->published()->create();
        $first = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 10:00:00'))
            ->create();
        $middle = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 12:00:00'))
            ->create();
        $last = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 14:00:00'))
            ->create();

        $firstEntity = $first instanceof Proxy ? $first->_real() : $first;
        $middleEntity = $middle instanceof Proxy ? $middle->_real() : $middle;
        $lastEntity = $last instanceof Proxy ? $last->_real() : $last;

        $repo = $this->repo(Happening::class);
        \assert($repo instanceof HappeningRepository);

        $result = $repo->findPreviousAndNext($middleEntity);

        self::assertSame([$firstEntity, $lastEntity], $result);
    }
}
