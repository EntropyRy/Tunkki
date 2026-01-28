<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\StreamArtist;
use App\Factory\ArtistFactory;
use App\Factory\MemberFactory;
use App\Factory\StreamFactory;
use App\Tests\Support\PantherTestCase;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('panther')]
final class StreamWorkflowPantherTest extends PantherTestCase
{
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
            ],
            [
                'webServerPort' => $this->getPantherWebServerPort(),
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
                WebDriverBy::cssSelector('.stream-artist-control')
            )
        );
        $this->assertSelectorExists('.stream-artist-control');

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
                WebDriverBy::cssSelector('.stream-artist-control .btn-danger')
            )
        );

        $container = $this->getPantherKernel()?->getContainer();
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

        $client->findElement(WebDriverBy::cssSelector('.stream-artist-control .btn-danger'))->click();
        $client->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.send-artist-form .btn-primary')
            )
        );

        $em->refresh($activeStreamArtist);
        self::assertNotNull($activeStreamArtist->getStoppedAt(), 'Artist should be removed from stream');

        $client->request('GET', "/profiili/artisti/{$artistId}/streamit");
        $heading = $client->getCrawler()->filter('h1')->text('');
        self::assertMatchesRegularExpression(
            '/'.preg_quote($artist->getName(), '/').'/i',
            $heading,
        );
    }
}
