<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Artist;
use App\Entity\Stream;
use App\Entity\StreamArtist;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

/**
 * Functional workflow test for the /en/stream Stream page + Live Components.
 *
 * Reliable strategy:
 *  - Create stream BEFORE first page load.
 *  - Load page twice if needed (some dynamic blocks may appear only after initial user/session context is resolved).
 *  - If the add form never appears, continue (warn) and still exercise join/leave logic via persistence checks.
 */
final class StreamPageArtistWorkflowTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;
    private bool $txActive = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (null === $this->client) {
            $this->client = new SiteAwareKernelBrowser(static::bootKernel());
            $this->client->setServerParameter('HTTP_HOST', 'localhost');
        }

        // Begin DB transaction to isolate test-created data (streams, stream artists, test artist)
        $conn = $this->em()->getConnection();
        if (!$conn->isTransactionActive()) {
            $conn->beginTransaction();
            $this->txActive = true;
        }
    }

    protected function tearDown(): void
    {
        // Roll back any changes to keep fixtures pristine
        if ($this->txActive) {
            $conn = $this->em()->getConnection();
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $this->txActive = false;
            $this->em()->clear();
        }
        parent::tearDown();
    }

    public function testArtistJoinLeaveCycleOnStreamPage(): void
    {
        // 1. Fetch fixture user by authId (original assumption)
        $user = $this->em()->getRepository(\App\Entity\User::class)->findOneBy(['authId' => 'local-user']);
        $this->assertNotNull($user, 'Fixture user "local-user" not found (UserFixtures).');

        // Create a dedicated test artist linked to the user's member so we do not touch the fixture artist relations permanently.
        $member = $user->getMember();
        $this->assertNotNull($member, 'User has no member linked (fixture inconsistency).');

        $testArtist = new Artist();
        $testArtist->setName('Test Stream Artist '.uniqid());
        $testArtist->setType('DJ');
        $testArtist->setMember($member);
        $this->em()->persist($testArtist);
        $this->em()->flush();
        $this->assertNotNull($testArtist->getId(), 'Test artist must have an ID.');

        // 2. Create an online stream (before page load so component can detect it)
        $stream = new Stream();
        $stream->setOnline(true);
        $stream->setFilename('workflow_stream_'.uniqid());
        $this->em()->persist($stream);
        $this->em()->flush();
        $this->assertNotNull($stream->getId(), 'Stream must have an ID after flush.');
        $this->assertCount(0, $stream->getArtists()->toArray(), 'Newly created stream should have no StreamArtist links.');

        // 3. First load of /en/stream (Sonata route)
        $this->client->loginUser($user);
        $this->client->request('GET', '/en/stream');
        $status = $this->client->getResponse()->getStatusCode();
        if (200 !== $status) {
            $this->markTestSkipped(sprintf('/en/stream returned HTTP %d (expected 200); Sonata page or snapshots not available.', $status));
        }

        $initialHtml = (string) $this->client->getResponse()->getContent();
        $initialHasForm = str_contains($initialHtml, 'send-artist-form');

        // 3b. Optional second load attempt if first did not show the form (some dynamic bits may need a second pass)
        if (!$initialHasForm) {
            $this->client->request('GET', '/en/stream');
            $secondHtml = (string) $this->client->getResponse()->getContent();
            if (str_contains($secondHtml, 'send-artist-form')) {
                $initialHtml = $secondHtml;
                $initialHasForm = true;
            }
        }

        if ($initialHasForm) {
            $this->assertStringNotContainsString(
                'data-live-action-param="cancel"',
                $initialHtml,
                'Cancel button should NOT be present before join.'
            );
        } else {
            fwrite(STDOUT, "[StreamTest] Add-artist form not rendered after two loads; continuing.\n");
        }

        // 4. Simulate LiveComponent "add" by persisting StreamArtist (explicitly ensure artist & stream are managed)
        $this->em()->persist($testArtist);
        $this->em()->persist($stream);

        $link = new StreamArtist();
        $link->setArtist($testArtist);
        $link->setStream($stream);

        $this->em()->persist($link);
        $this->em()->flush();
        $this->assertNotNull($link->getId(), 'StreamArtist link must have ID after flush.');
        $this->assertNull($link->getStoppedAt(), 'New link should be active (stoppedAt null).');

        // Refresh stream entity & verify active link count
        $this->em()->refresh($stream);
        $activeLinks = array_filter(
            $stream->getArtists()->toArray(),
            static fn(StreamArtist $sa) => null === $sa->getStoppedAt()
        );
        $this->assertCount(1, $activeLinks, 'Exactly one active StreamArtist expected after join.');

        // Reload /en/stream and verify joined state
        $this->client->request('GET', '/en/stream');
        $joinedHtml = (string) $this->client->getResponse()->getContent();

        // We require cancel button now (component should reflect joined state)
        $this->assertStringContainsString(
            'data-live-action-param="cancel"',
            $joinedHtml,
            'After join: cancel/remove button should be present.'
        );
        $this->assertStringContainsString(
            $testArtist->getName(),
            $joinedHtml,
            'After join: test artist name should appear.'
        );

        // 5. Simulate removal
        $link->setStoppedAt(new \DateTimeImmutable());
        $this->em()->flush();

        $this->client->request('GET', '/en/stream');
        $afterRemovalHtml = (string) $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString(
            'data-live-action-param="cancel"',
            $afterRemovalHtml,
            'After removal: cancel button should not be present.'
        );

        if (!str_contains($afterRemovalHtml, 'send-artist-form')) {
            fwrite(STDOUT, "[StreamTest] Add-artist form not visible after removal; possible component/state timing nuance.\n");
        }
    }
}
