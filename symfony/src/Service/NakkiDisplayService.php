<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\NakkiBooking;

/**
 * NakkiDisplayService - Handles display logic for volunteer nakki bookings.
 *
 * Extracted from ShopsController and EventTicketController to eliminate duplication.
 */
class NakkiDisplayService
{
    /**
     * Get available nakki bookings for a member at an event.
     *
     * @param Event $event The event
     * @param Member $member The member
     * @param array<NakkiBooking> $selected Already selected bookings
     * @param string $locale The locale for name/description
     * @return array<string, mixed> Array of nakki information grouped by name
     */
    public function getNakkiFromGroup(
        Event $event,
        Member $member,
        array $selected,
        string $locale,
    ): array {
        $nakkis = [];
        foreach ($event->getNakkis() as $nakki) {
            if (true == $nakki->isDisableBookings()) {
                continue;
            }
            foreach ($selected as $booking) {
                if ($booking->getNakki() == $nakki) {
                    $nakkis = $this->addNakkiToArray(
                        $nakkis,
                        $booking,
                        $locale,
                    );
                    break;
                }
            }
            if (
                !\array_key_exists(
                    $nakki->getDefinition()->getName($locale),
                    $nakkis,
                )
            ) {
                // try to prevent displaying same nakki to 2 different users using the system at the same time
                $bookings = $nakki->getNakkiBookings()->toArray();
                shuffle($bookings);
                foreach ($bookings as $booking) {
                    if (null === $booking->getMember()) {
                        $nakkis = $this->addNakkiToArray(
                            $nakkis,
                            $booking,
                            $locale,
                        );
                        break;
                    }
                }
            }
        }

        return $nakkis;
    }

    /**
     * Add a nakki booking to the nakkis array.
     *
     * @param array<string, mixed> $nakkis The nakkis array
     * @param NakkiBooking $booking The booking to add
     * @param string $locale The locale for name/description
     * @return array<string, mixed> The updated nakkis array
     */
    public function addNakkiToArray(array $nakkis, NakkiBooking $booking, string $locale): array
    {
        $name = $booking->getNakki()->getDefinition()->getName($locale);
        $duration = $booking
            ->getStartAt()
            ->diff($booking->getEndAt())
            ->format('%h');
        $nakkis[$name]['description'] = $booking
            ->getNakki()
            ->getDefinition()
            ->getDescription($locale);
        $nakkis[$name]['bookings'][] = $booking;
        $nakkis[$name]['durations'][$duration] = $duration;

        return $nakkis;
    }
}
