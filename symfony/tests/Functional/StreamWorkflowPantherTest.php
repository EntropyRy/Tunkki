<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\StreamArtist;
use App\Factory\ArtistFactory;
use App\Factory\MemberFactory;
use App\Factory\StreamFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Process\Process;
use Zenstruck\Foundry\Test\Factories;

final class StreamWorkflowPantherTest extends PantherTestCase
{
    use Factories;

    private static ?KernelInterface $pantherKernel = null;
    private static bool $driversInstalled = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$driversInstalled) {
            $process = new Process(['vendor/bin/bdi', 'detect', 'drivers']);
            $process->setWorkingDirectory(dirname(__DIR__, 2));
            $process->mustRun();
            self::$driversInstalled = true;
        }

        $this->bootstrapPantherEnvironment();
    }

    protected function tearDown(): void
    {
        if (self::$pantherKernel !== null) {
            self::$pantherKernel->shutdown();
            self::$pantherKernel = null;
        }

        parent::tearDown();
    }

    #[Test]
    public function userCanAddAndRemoveArtistFromStream(): void
    {
        $member = MemberFactory::new()->active()->create();
        $artist = ArtistFactory::new()
            ->withMember($member)
            ->dj()
            ->create();
        $stream = StreamFactory::new()->online()->create();

        $artistId = $artist->getId();
        $memberId = $member->getId();
        $streamId = $stream->getId();
        $userEmail = $member->getEmail();

        $client = static::createPantherClient(
            [
                'browser' => static::CHROME,
                'hostname' => 'localhost',
            ],
            [
                'environment' => 'panther',
            ]
        );

        $client->request('GET', '/login');
        $client->waitFor('form');
        $client->submitForm('Kirjaudu sisään', [
            '_username' => $userEmail,
            '_password' => 'password',
        ]);
        sleep(2);

        $client->request('GET', '/stream');
        $client->wait(15)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::xpath('//*[@data-live-name-value="Stream:ArtistControl"]')
            )
        );
        $this->assertSelectorExists('[data-live-name-value="Stream:ArtistControl"]');

        $client->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.stream-artist-control select')
            )
        );
        $client->submitForm('Lisää streamiin', [
            'stream_artist[artist]' => (string) $artistId,
        ]);
        $client->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('button[data-live-action-param="cancel"]')
            )
        );

        $container = self::$pantherKernel?->getContainer();
        self::assertNotNull($container, 'Panther kernel should be booted');

        $em = $container->get('doctrine')->getManager();
        $streamArtistRepo = $em->getRepository(StreamArtist::class);
        $memberEntity = $em->find(\App\Entity\Member::class, $memberId);
        $streamEntity = $em->find(\App\Entity\Stream::class, $streamId);

        $activeStreamArtist = $streamArtistRepo->findActiveMemberArtistInStream(
            $memberEntity,
            $streamEntity
        );

        self::assertNotNull($activeStreamArtist, 'Artist should be active in stream');
        self::assertNull($activeStreamArtist->getStoppedAt(), 'Stream artist should not have stoppedAt');
        self::assertSame($artistId, $activeStreamArtist->getArtist()->getId());

        $client->findElement(WebDriverBy::cssSelector('button[data-live-action-param="cancel"]'))->click();
        $client->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.send-artist-form button[type="submit"]')
            )
        );

        $em->refresh($activeStreamArtist);
        self::assertNotNull($activeStreamArtist->getStoppedAt(), 'Artist should be removed from stream');

        $client->request('GET', "/profiili/artisti/{$artistId}/streamit");
        $heading = $client->getCrawler()->filter('h1')->text('');
        self::assertStringContainsStringIgnoringCase($artist->getName(), $heading);
    }

    private function bootstrapPantherEnvironment(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $filesystem = new Filesystem();
        $cachePath = $projectDir.'/var/cache/panther';
        $dbPath = $projectDir.'/var/test_panther.db';

        self::ensureKernelShutdown();

        if (self::$pantherKernel !== null) {
            self::$pantherKernel->shutdown();
            self::$pantherKernel = null;
        }

        if ($filesystem->exists($cachePath)) {
            $filesystem->remove($cachePath);
        }

        if ($filesystem->exists($dbPath)) {
            $filesystem->remove($dbPath);
        }

        $_ENV['APP_ENV'] = 'panther';
        $_SERVER['APP_ENV'] = 'panther';
        putenv('APP_ENV=panther');
        $_ENV['DATABASE_URL'] = 'sqlite:///'.$dbPath;
        $_SERVER['DATABASE_URL'] = 'sqlite:///'.$dbPath;
        putenv('DATABASE_URL=sqlite:///'.$dbPath);

        $kernel = new \App\Kernel('panther', true);
        $kernel->boot();
        self::$pantherKernel = $kernel;

        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        if ($metadata !== []) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metadata);
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        foreach ([
            'entropy:cms:seed',
            'sonata:page:update-core-routes',
            'sonata:page:create-snapshots',
        ] as $command) {
            $application->run(new ArrayInput(['command' => $command]), new NullOutput());
        }
    }
}
