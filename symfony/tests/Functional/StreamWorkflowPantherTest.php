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
    private static array $previousEnv = [];
    private static array $previousServer = [];
    private static array $previousGetEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$driversInstalled) {
            $process = new Process(['vendor/bin/bdi', 'detect', 'drivers']);
            $process->setWorkingDirectory(\dirname(__DIR__, 2));
            $process->mustRun();
            self::$driversInstalled = true;
        }

        $this->bootstrapPantherEnvironment();
    }

    protected function tearDown(): void
    {
        $this->restoreOriginalEnvironment();

        if (null !== self::$pantherKernel) {
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
        $projectDir = \dirname(__DIR__, 2);
        $filesystem = new Filesystem();
        $cachePath = $projectDir.'/var/cache/panther';
        $dbPath = $projectDir.'/var/test_panther.db';

        self::ensureKernelShutdown();

        if (null !== self::$pantherKernel) {
            self::$pantherKernel->shutdown();
            self::$pantherKernel = null;
        }

        if ($filesystem->exists($cachePath)) {
            $filesystem->remove($cachePath);
        }

        if ($filesystem->exists($dbPath)) {
            $filesystem->remove($dbPath);
        }

        self::$previousEnv = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? null,
            'DATABASE_URL' => $_ENV['DATABASE_URL'] ?? null,
        ];
        self::$previousServer = [
            'APP_ENV' => $_SERVER['APP_ENV'] ?? null,
            'DATABASE_URL' => $_SERVER['DATABASE_URL'] ?? null,
        ];
        self::$previousGetEnv = [
            'APP_ENV' => false !== getenv('APP_ENV') ? getenv('APP_ENV') : null,
            'DATABASE_URL' => false !== getenv('DATABASE_URL') ? getenv('DATABASE_URL') : null,
        ];

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
        if ([] !== $metadata) {
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($metadata);
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        foreach ([
            'entropy:cms:seed',
            'sonata:page:update-core-routes',
        ] as $command) {
            $application->run(new ArrayInput(['command' => $command]), new NullOutput());
        }
    }

    private function restoreOriginalEnvironment(): void
    {
        if ([] === self::$previousEnv && [] === self::$previousServer && [] === self::$previousGetEnv) {
            return;
        }

        if (array_key_exists('APP_ENV', self::$previousEnv)) {
            $value = self::$previousEnv['APP_ENV'];
            if (null === $value) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $value;
            }
        }
        if (array_key_exists('DATABASE_URL', self::$previousEnv)) {
            $value = self::$previousEnv['DATABASE_URL'];
            if (null === $value) {
                unset($_ENV['DATABASE_URL']);
            } else {
                $_ENV['DATABASE_URL'] = $value;
            }
        }

        if (array_key_exists('APP_ENV', self::$previousServer)) {
            $value = self::$previousServer['APP_ENV'];
            if (null === $value) {
                unset($_SERVER['APP_ENV']);
            } else {
                $_SERVER['APP_ENV'] = $value;
            }
        }
        if (array_key_exists('DATABASE_URL', self::$previousServer)) {
            $value = self::$previousServer['DATABASE_URL'];
            if (null === $value) {
                unset($_SERVER['DATABASE_URL']);
            } else {
                $_SERVER['DATABASE_URL'] = $value;
            }
        }

        if (array_key_exists('APP_ENV', self::$previousGetEnv)) {
            $value = self::$previousGetEnv['APP_ENV'];
            putenv(null === $value ? 'APP_ENV' : 'APP_ENV='.$value);
        }
        if (array_key_exists('DATABASE_URL', self::$previousGetEnv)) {
            $value = self::$previousGetEnv['DATABASE_URL'];
            putenv(null === $value ? 'DATABASE_URL' : 'DATABASE_URL='.$value);
        }

        self::$previousEnv = self::$previousServer = self::$previousGetEnv = [];
    }
}
