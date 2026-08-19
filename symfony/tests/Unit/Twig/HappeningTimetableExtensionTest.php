<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\Happening;
use App\Twig\HappeningTimeGroup;
use App\Twig\HappeningTimetableExtension;
use PHPUnit\Framework\TestCase;

final class HappeningTimetableExtensionTest extends TestCase
{
    private HappeningTimetableExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new HappeningTimetableExtension();
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        self::assertSame([], $this->extension->groupByTime([]));
    }

    public function testSingleHappeningReturnsSingleGroup(): void
    {
        $happening = $this->happeningAt('2030-01-01 17:00:00');

        $groups = $this->extension->groupByTime([$happening]);

        self::assertCount(1, $groups);
        self::assertSame([$happening], $groups[0]->happenings);
    }

    public function testConsecutiveSameTimeHappeningsAreGrouped(): void
    {
        $first = $this->happeningAt('2030-01-01 17:00:00');
        $second = $this->happeningAt('2030-01-01 17:00:00');
        $third = $this->happeningAt('2030-01-01 17:00:00');

        $groups = $this->extension->groupByTime([$first, $second, $third]);

        self::assertCount(1, $groups);
        self::assertSame([$first, $second, $third], $groups[0]->happenings);
    }

    public function testDifferentTimeStartsANewGroup(): void
    {
        $first = $this->happeningAt('2030-01-01 17:00:00');
        $second = $this->happeningAt('2030-01-01 18:00:00');

        $groups = $this->extension->groupByTime([$first, $second]);

        self::assertCount(2, $groups);
        self::assertSame([$first], $groups[0]->happenings);
        self::assertSame([$second], $groups[1]->happenings);
    }

    public function testNonConsecutiveSameTimeHappeningsAreNotMerged(): void
    {
        $first = $this->happeningAt('2030-01-01 17:00:00');
        $middle = $this->happeningAt('2030-01-01 18:00:00');
        $last = $this->happeningAt('2030-01-01 17:00:00');

        $groups = $this->extension->groupByTime([$first, $middle, $last]);

        self::assertCount(3, $groups);
        self::assertSame([$first], $groups[0]->happenings);
        self::assertSame([$middle], $groups[1]->happenings);
        self::assertSame([$last], $groups[2]->happenings);
    }

    public function testGroupOrderMatchesInputOrder(): void
    {
        $first = $this->happeningAt('2030-01-01 17:00:00');
        $second = $this->happeningAt('2030-01-01 18:00:00');
        $third = $this->happeningAt('2030-01-01 19:00:00');

        $groups = $this->extension->groupByTime([$first, $second, $third]);

        self::assertSame(
            ['17:00:00', '18:00:00', '19:00:00'],
            array_map(
                static fn (HappeningTimeGroup $g): string => $g->time->format('H:i:s'),
                $groups,
            ),
        );
    }

    private function happeningAt(string $time): Happening
    {
        $happening = new Happening();
        $happening->setTime(new \DateTimeImmutable($time));

        return $happening;
    }
}
