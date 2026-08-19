<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Happening;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class HappeningTimetableExtension extends AbstractExtension
{
    /**
     * @return TwigFilter[]
     */
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('group_happenings_by_time', $this->groupByTime(...)),
        ];
    }

    /**
     * Groups a time-sorted list of happenings into consecutive runs that share
     * the exact same start time, so a timetable can show one time label per
     * group instead of repeating it for every simultaneous happening.
     *
     * @param iterable<Happening> $happenings
     *
     * @return list<HappeningTimeGroup>
     */
    public function groupByTime(iterable $happenings): array
    {
        $groups = [];

        foreach ($happenings as $happening) {
            $time = $happening->getTime();
            $lastIndex = \count($groups) - 1;

            if ($lastIndex >= 0 && $groups[$lastIndex]->time == $time) {
                $groups[$lastIndex] = new HappeningTimeGroup(
                    $groups[$lastIndex]->time,
                    [...$groups[$lastIndex]->happenings, $happening],
                );
            } else {
                $groups[] = new HappeningTimeGroup($time, [$happening]);
            }
        }

        return $groups;
    }
}
