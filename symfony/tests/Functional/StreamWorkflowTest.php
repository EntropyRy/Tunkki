<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\StreamArtist;
use App\Factory\ArtistFactory;
use App\Factory\MemberFactory;
use App\Factory\StreamArtistFactory;
use App\Factory\StreamFactory;
use App\Repository\StreamArtistRepository;
use App\Tests\Support\LoginHelperTrait;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Panther\PantherTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Tests the complete stream workflow using Panther (browser-based testing with JavaScript):
 * 1. User has an artist
 * 2. Stream is online
 * 3. User adds their artist to the stream via LiveComponent
 * 4. User removes their artist from the stream via LiveComponent
 * 5. User can see stream details in artist profile.
 *
 * This test executes real JavaScript and tests LiveComponent interactions end-to-end.
 *
 * @group panther
 */
final class StreamWorkflowTest extends PantherTestCase
{
    use Factories;
    use LoginHelperTrait;

    private static ?KernelInterface $pantherKernel = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create schema and seed CMS baseline in the panther environment
        $this->setupPantherDatabase();
    }

    protected function tearDown(): void
    {
        if (self::$pantherKernel !== null) {
            self::$pantherKernel->shutdown();
            self::$pantherKernel = null;
        }

        parent::tearDown();
    }

    /**
     * Setup the panther environment's database (SQLite file).
     * Creates schema and seeds CMS baseline.
     */
    private function setupPantherDatabase(): void
    {
        // Override DATABASE_URL for panther environment (SQLite file-based)
        // This is necessary because PHPUnit's bootstrap loads .env.test first
        $projectDir = dirname(__DIR__, 2);
        $databasePath = $projectDir . '/var/test_panther.db';
        $_ENV['DATABASE_URL'] = "sqlite:///{$databasePath}";
        $_SERVER['DATABASE_URL'] = "sqlite:///{$databasePath}";
        putenv("DATABASE_URL=sqlite:///{$databasePath}");

        // Boot a kernel in panther environment
        self::$pantherKernel = new \App\Kernel('panther', true);
        self::$pantherKernel->boot();

        // Create Doctrine schema
        $application = new Application(self::$pantherKernel);
        $application->setAutoExit(false);

        // Create schema
        $input = new ArrayInput([
            'command' => 'doctrine:schema:create',
        ]);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitCode = $application->run($input, $output);
        echo "Schema create exit code: {$exitCode}\n";
        echo $output->fetch();

        // Seed CMS baseline using console command in panther environment
        $input = new ArrayInput([
            'command' => 'entropy:cms:seed',
        ]);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitCode = $application->run($input, $output);
        echo "CMS seed exit code: {$exitCode}\n";
        echo $output->fetch();
    }

    #[Test]
    public function userCanAddAndRemoveArtistFromStream(): void
    {
        // Given: A member with an artist (created with Foundry in panther DB)
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()
            ->withMember($member)
            ->dj()
            ->create();

        // And: An online stream
        $stream = StreamFactory::new()->online()->create();

        // Store IDs for later verification
        $artistId = $artist->getId();
        $memberId = $member->getId();
        $streamId = $stream->getId();
        $userEmail = $member->getEmail();

        // Create Panther client (headless browser with JavaScript support)
        $client = static::createPantherClient([
            'browser' => static::CHROME,
        ]);

        // When: User logs in and visits the stream page
        $client->request('GET', '/login');

        // Debug: Save login page source
        file_put_contents('/var/www/symfony/var/test_login_page.html', $client->getPageSource());

        // Wait for login form to load and fill it in
        $client->waitFor('input[name="_username"]');
        $client->findElement(\Facebook\WebDriver\WebDriverBy::name('_username'))->sendKeys($userEmail);
        $client->findElement(\Facebook\WebDriver\WebDriverBy::name('_password'))->sendKeys('password');

        // Submit the form by clicking the submit button
        $submitButton = $client->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('form button[type="submit"]'));
        $submitButton->click();

        // Wait a moment for login to process
        sleep(2);

        // Visit the stream page
        $client->request('GET', '/stream');

        // Debug: Save page to check what's rendered
        file_put_contents('/var/www/symfony/var/test_stream_page.html', $client->getPageSource());

        // Wait for the LiveComponent to fully load
        $client->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('[data-live-name-value="Stream:ArtistControl"]')
            )
        );

        // Then: The page should load and the artist control component should be present
        $this->assertSelectorExists('[data-live-name-value="Stream:ArtistControl"]');

        // When: User interacts with the LiveComponent to add their artist to the stream
        // Wait for the form to be present and find the artist select field
        $client->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.stream-artist-control select')
            )
        );

        $artistSelect = $client->findElement(WebDriverBy::cssSelector('.stream-artist-control select'));
        $artistSelect->sendKeys((string) $artistId);

        // Find and click the submit button
        $submitButton = $client->findElement(WebDriverBy::cssSelector('.send-artist-form button[type="submit"]'));
        $submitButton->click();

        // Wait for LiveComponent AJAX to complete and UI to update (remove button should appear)
        $client->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('button[data-live-action-param="cancel"]')
            )
        );

        // Then: The artist should now be in the stream (verify in database)
        $em = self::$pantherKernel->getContainer()->get('doctrine')->getManager();
        $streamArtistRepo = self::$pantherKernel->getContainer()->get(StreamArtistRepository::class);
        $memberEntity = $em->find(\App\Entity\Member::class, $memberId);
        $streamEntity = $em->find(\App\Entity\Stream::class, $streamId);

        $activeStreamArtist = $streamArtistRepo->findActiveMemberArtistInStream(
            $memberEntity,
            $streamEntity
        );

        $this->assertNotNull($activeStreamArtist, 'Artist should be active in stream');
        $this->assertNull($activeStreamArtist->getStoppedAt(), 'Stream artist should not have stoppedAt');
        $this->assertSame($artistId, $activeStreamArtist->getArtist()->getId());

        // When: User removes their artist from the stream by clicking the remove button
        $removeButton = $client->findElement(WebDriverBy::cssSelector('button[data-live-action-param="cancel"]'));
        $removeButton->click();

        // Wait for LiveComponent AJAX to complete and UI to update (form should appear again)
        $client->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.send-artist-form button[type="submit"]')
            )
        );

        // Then: The stream artist should be stopped
        $em->refresh($activeStreamArtist);
        $this->assertNotNull($activeStreamArtist->getStoppedAt(), 'Artist should be removed from stream');

        // When: User visits their artist's streams page
        $client->request('GET', "/profiili/artisti/{$artistId}/streamit");

        // Then: The page should show the artist's name
        $artistEntity = $em->find(\App\Entity\Artist::class, $artistId);
        $this->assertSelectorTextContains('h1', $artistEntity->getName());
    }

    #[Test]
    public function userCanSeeStreamDetailsInArtistProfile(): void
    {
        // Given: A member with an artist
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()
            ->withMember($member)
            ->band()
            ->create();

        // And: An online stream
        $stream = StreamFactory::new()->online()->create();

        // And: The artist has a past stream session (already stopped)
        $pastStreamArtist = StreamArtistFactory::new()
            ->forArtist($artist)
            ->inStream($stream)
            ->stopped()
            ->create();

        // And: User is logged in
        $this->loginAsMember($member->getEmail());

        // When: User visits their artist's streams page
        $artistId = $artist->getId();
        $this->client->request('GET', "/profiili/artisti/{$artistId}/streamit");

        // Then: The page should load successfully
        $this->assertResponseIsSuccessful();

        // And: The page should show the artist's streams
        $this->client->assertSelectorTextContains('h1', $artist->getName());
    }

    #[Test]
    public function userCannotAccessOtherMembersArtistStreams(): void
    {
        // Given: Two members with artists
        $member1 = MemberFactory::new()->active()->create();
        $artist1 = ArtistFactory::new()
            ->withMember($member1)
            ->create();

        $member2 = MemberFactory::new()->active()->create();
        $artist2 = ArtistFactory::new()
            ->withMember($member2)
            ->create();

        // And: User 1 is logged in
        $this->loginAsMember($member1->getEmail());
        $this->seedClientHome('fi');

        // When: User 1 tries to access User 2's artist streams
        $artist2Id = $artist2->getId();
        $this->client->request('GET', "/profiili/artisti/{$artist2Id}/streamit");

        // Then: User should be redirected (access denied)
        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode(), 'Should redirect when accessing other member\'s artist');
        $this->assertStringContainsString('/profiili/artisti', $response->headers->get('Location') ?? '');

        // And: Following the redirect should show the artist list
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function multipleArtistsCanBeInStreamSimultaneously(): void
    {
        // Given: Two members with artists
        $member1 = MemberFactory::new()->active()->create();
        $artist1 = ArtistFactory::new()
            ->withMember($member1)
            ->dj()
            ->create();

        $member2 = MemberFactory::new()->active()->create();
        $artist2 = ArtistFactory::new()
            ->withMember($member2)
            ->band()
            ->create();

        // And: An online stream
        $stream = StreamFactory::new()->online()->create();

        // When: Both artists are added to the stream
        $streamArtist1 = StreamArtistFactory::new()
            ->forArtist($artist1)
            ->inStream($stream)
            ->active()
            ->create();

        $streamArtist2 = StreamArtistFactory::new()
            ->forArtist($artist2)
            ->inStream($stream)
            ->active()
            ->create();

        // Then: Both stream artists should be active
        $this->assertNull($streamArtist1->getStoppedAt());
        $this->assertNull($streamArtist2->getStoppedAt());

        // And: Both should be in the same stream
        $this->assertSame($stream->getId(), $streamArtist1->getStream()->getId());
        $this->assertSame($stream->getId(), $streamArtist2->getStream()->getId());
    }

    #[Test]
    public function streamPageAccessibleWhenNoStreamIsOnline(): void
    {
        // Given: A member with an artist
        $member = MemberFactory::new()->active()->create();
        ArtistFactory::new()
            ->withMember($member)
            ->create();

        // And: No online stream exists
        // (default state - no streams created)

        // And: User is logged in
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        // When: User visits the stream page
        $this->client->request('GET', '/stream');

        // Then: The page should still load successfully
        $this->assertResponseIsSuccessful();

        // And: The artist control component should be present
        // (but may show different UI state)
        $this->client->assertSelectorExists('[data-live-name-value="Stream:ArtistControl"]');
    }

    #[Test]
    public function englishStreamRoutesWork(): void
    {
        // Given: A member with an artist
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()
            ->withMember($member)
            ->create();

        // And: User is logged in
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        // When: User visits the English stream page
        $this->client->request('GET', '/en/stream');

        // Then: The page should load successfully
        $this->assertResponseIsSuccessful();

        // When: User visits artist streams in English
        $artistId = $artist->getId();
        $this->client->request('GET', "/en/profile/artist/{$artistId}/streams");

        // Then: The page should load successfully
        $this->assertResponseIsSuccessful();

        // And: The page should show the artist's streams
        $this->client->assertSelectorTextContains('h1', $artist->getName());
    }
}
