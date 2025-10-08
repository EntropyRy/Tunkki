<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Event;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * Verifies that after removing BackgroundEffectConfigProvider:
 *  - Background effect config JSON is persisted verbatim (no server-side normalization / pretty-printing).
 *  - Updating the config preserves the exact raw string.
 *  - Unsupported effects (e.g. "snake") do NOT automatically clear the config when persisted programmatically.
 *
 * NOTE: These tests exercise persistence at the entity level (Doctrine). They do NOT simulate the Sonata
 *       admin form listeners (which still apply some allow‑list logic for configurable effects on submit).
 */
final class BackgroundEffectConfigTest extends FixturesWebTestCase
{
    public function testConfigPersistsVerbatimForConfigurableEffect(): void
    {
        $em = $this->em();

        $event = new Event();
        $event
            ->setName('Config Test EN')
            ->setNimi('Config Test FI')
            ->setType('event')
            ->setEventDate(new \DateTimeImmutable('+2 days'))
            ->setPublished(true)
            ->setBackgroundEffect('flowfields');

        // Intentionally unsorted keys, extra spaces, newline at end to confirm no normalization occurs.
        $rawJson = "{ \"z\":1,  \"a\": 2,\n\"nested\": {\"b\":3}}\n";

        $event->setBackgroundEffectConfig($rawJson);

        $em->persist($event);
        $em->flush();

        $id = $event->getId();
        self::assertNotNull($id, 'Event ID should be assigned after flush.');

        // Detach to ensure we re-read from the DB, not the in-memory instance.
        $em->clear();

        /** @var Event $reloaded */
        $reloaded = $em->getRepository(Event::class)->find($id);
        self::assertNotNull($reloaded, 'Reloaded event must exist.');

        self::assertSame(
            $rawJson,
            $reloaded->getBackgroundEffectConfig(),
            'BackgroundEffectConfig should be stored verbatim (no pretty-print or key sorting).',
        );

        // Now update with a different raw structure (different spacing + added key)
        $updatedJson = '{"z":1,"a":2,"nested":{"b":3},"extra":[3,2,1] }';
        $reloaded->setBackgroundEffectConfig($updatedJson);
        $em->flush();
        $em->clear();

        /** @var Event $reloaded2 */
        $reloaded2 = $em->getRepository(Event::class)->find($id);
        self::assertNotNull($reloaded2);

        self::assertSame(
            $updatedJson,
            $reloaded2->getBackgroundEffectConfig(),
            'Updated raw JSON must remain exactly as provided (no normalization).',
        );

        // Note: Test data cleanup is handled by tearDown() clearing EntityManager state.
        // Actual DB rows remain but fixtures are reloaded before each test run.
    }

    public function testUnsupportedEffectDoesNotAutomaticallyClearConfigOnEntityPersist(): void
    {
        $em = $this->em();

        $event = new Event();
        $event
            ->setName('Unsupported Effect Test EN')
            ->setNimi('Unsupported Effect Test FI')
            ->setType('event')
            ->setEventDate(new \DateTimeImmutable('+3 days'))
            ->setPublished(true)
            ->setBackgroundEffect('snake'); // Not in the old provider allow-list
        $json = '{"foo":"bar","list":[1,2,3]}';
        $event->setBackgroundEffectConfig($json);

        $em->persist($event);
        $em->flush();
        $id = $event->getId();
        self::assertNotNull($id);

        $em->clear();

        /** @var Event $reloaded */
        $reloaded = $em->getRepository(Event::class)->find($id);
        self::assertNotNull($reloaded);

        self::assertSame(
            'snake',
            $reloaded->getBackgroundEffect(),
            'Effect should persist even if unsupported at admin UI level.',
        );
        self::assertSame(
            $json,
            $reloaded->getBackgroundEffectConfig(),
            'Config for unsupported effect should remain (only admin form listener clears it, not entity persistence).',
        );

        // Note: Test data cleanup is handled by tearDown() clearing EntityManager state.
        // Actual DB rows remain but fixtures are reloaded before each test run.
    }
}
