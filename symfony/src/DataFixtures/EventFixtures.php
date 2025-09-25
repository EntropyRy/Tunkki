<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Unified Event fixtures covering multiple scenarios:
 * - Published upcoming event (canonical test target)
 * - Unpublished event (should be hidden from anonymous users)
 * - Past event (already happened, useful for archive behavior)
 * - External URL event (controller should redirect to external link)
 * - Tickets-enabled event (shop/tickets flow enabled)
 */
final class EventFixtures extends Fixture
{
    // Keep original reference name for backward compatibility with older tests
    public const TEST_EVENT = "test_event";

    public const UNPUBLISHED_EVENT = "event_unpublished";
    public const PAST_EVENT = "event_past";
    public const EXTERNAL_EVENT = "event_external";
    public const TICKETS_EVENT = "event_tickets";

    public function load(ObjectManager $manager): void
    {
        // 1) Canonical published event (used by functional tests)
        $test = new Event();
        $test->setName("Test Event");
        $test->setNimi("Testitapahtuma");
        $test->setType("event");
        $test->setPublishDate(new \DateTimeImmutable("-1 hour"));
        $test->setEventDate(new \DateTimeImmutable("+1 day")->setTime(20, 0));
        $test->setPublished(true);
        $test->setUrl("test-event");
        $test->setTemplate("event.html.twig");
        $test->setContent("<p>Test content for the event (EN)</p>");
        $test->setSisallys("<p>Testisisältö tapahtumalle (FI)</p>");
        $manager->persist($test);
        $this->addReference(self::TEST_EVENT, $test);

        // 2) Unpublished event (should not be visible to anonymous users)
        $unpublished = new Event();
        $unpublished->setName("Unpublished Event");
        $unpublished->setNimi("Julkaisematon tapahtuma");
        $unpublished->setType("event");
        $unpublished->setPublishDate(new \DateTimeImmutable("-1 day"));
        $unpublished->setEventDate(
            new \DateTimeImmutable("+2 days")->setTime(20, 0),
        );
        $unpublished->setPublished(false);
        $unpublished->setUrl("unpublished-event");
        $unpublished->setTemplate("event.html.twig");
        $unpublished->setContent(
            "<p>EN: This event is intentionally unpublished.</p>",
        );
        $unpublished->setSisallys(
            "<p>FI: Tämä tapahtuma on tarkoituksella julkaisematon.</p>",
        );
        $manager->persist($unpublished);
        $this->addReference(self::UNPUBLISHED_EVENT, $unpublished);

        // 3) Past event (already happened, but remains public for archives)
        $past = new Event();
        $past->setName("Past Event");
        $past->setNimi("Mennyt tapahtuma");
        $past->setType("event");
        $past->setPublishDate(new \DateTimeImmutable("-10 days"));
        $past->setEventDate(new \DateTimeImmutable("-1 day")->setTime(20, 0));
        $past->setPublished(true);
        $past->setUrl("past-event");
        $past->setTemplate("event.html.twig");
        $past->setContent("<p>EN: This event is in the past.</p>");
        $past->setSisallys("<p>FI: Tämä tapahtuma on menneisyydessä.</p>");
        $manager->persist($past);
        $this->addReference(self::PAST_EVENT, $past);

        // 4) External URL event (controller should redirect to Event::getUrl())
        $external = new Event();
        $external->setName("External URL Event");
        $external->setNimi("Ulkoinen tapahtuma");
        $external->setType("event");
        $external->setPublishDate(new \DateTimeImmutable("-1 hour"));
        $external->setEventDate(
            new \DateTimeImmutable("+3 days")->setTime(18, 0),
        );
        $external->setPublished(true);
        // If the entity supports marking an event as "external", set the flag
        if (method_exists($external, "setExternalUrl")) {
            $external->setExternalUrl(true);
        }
        // For external events, the controller uses the value of getUrl() as the redirect target
        $external->setUrl("https://example.com/external-event");
        $external->setTemplate("event.html.twig");
        $external->setContent("<p>EN: This event redirects externally.</p>");
        $external->setSisallys(
            "<p>FI: Tämä tapahtuma uudelleenohjaa ulkoiseen osoitteeseen.</p>",
        );
        $manager->persist($external);
        $this->addReference(self::EXTERNAL_EVENT, $external);

        // 5) Tickets-enabled event (shop flow/pages enabled when supported)
        $tickets = new Event();
        $tickets->setName("Tickets Enabled Event");
        $tickets->setNimi("Lippuja saatavilla -tapahtuma");
        $tickets->setType("event");
        $tickets->setPublishDate(new \DateTimeImmutable("-2 hours"));
        $tickets->setEventDate(
            new \DateTimeImmutable("+5 days")->setTime(21, 0),
        );
        $tickets->setPublished(true);
        $tickets->setUrl("tickets-event");
        $tickets->setTemplate("event.html.twig");
        $tickets->setContent("<p>EN: Tickets are enabled for this event.</p>");
        $tickets->setSisallys(
            "<p>FI: Tähän tapahtumaan on saatavilla lippuja.</p>",
        );
        if (method_exists($tickets, "setTicketsEnabled")) {
            $tickets->setTicketsEnabled(true);
        }
        if (method_exists($tickets, "setTicketPresaleEnabled")) {
            $tickets->setTicketPresaleEnabled(true);
        }
        if (method_exists($tickets, "setNakkiRequiredForTicketReservation")) {
            $tickets->setNakkiRequiredForTicketReservation(false);
        }
        $manager->persist($tickets);
        $this->addReference(self::TICKETS_EVENT, $tickets);

        // Persist all
        // Shop-ready event with tickets product and presale window
        $shop = new Event();
        $shop->setName("Shop Ready Event");
        $shop->setNimi("Kauppa valmis tapahtuma");
        $shop->setType("event");
        $shop->setPublishDate(new \DateTimeImmutable("-1 hour"));
        $shop->setEventDate(new \DateTimeImmutable("+7 days")->setTime(20, 0));
        $shop->setPublished(true);
        $shop->setUrl("shop-event");
        $shop->setTemplate("event.html.twig");
        $shop->setContent("<p>EN: Shop-ready event.</p>");
        $shop->setSisallys("<p>FI: Kauppa valmis tapahtuma.</p>");
        $shop->setTicketsEnabled(true);
        $shop->setTicketPresaleStart(new \DateTimeImmutable("-1 hour"));
        $shop->setTicketPresaleEnd(new \DateTimeImmutable("+1 day"));

        $ticket = new Product();
        $ticket->setStripeId("test_ticket_product");
        $ticket->setStripePriceId("test_ticket_price");
        $ticket->setQuantity(100);
        $ticket->setAmount(1500);
        $ticket->setTicket(true);
        $ticket->setNameEn("General admission");
        $ticket->setNameFi("Peruslippu");
        $ticket->setDescriptionEn("Test ticket EN");
        $ticket->setDescriptionFi("Testilippu FI");
        $ticket->setHowManyOneCanBuyAtOneTime(2);

        $shop->addProduct($ticket);
        $manager->persist($shop);
        $manager->persist($ticket);

        $manager->flush();
    }
}
