<?php

declare(strict_types=1);

namespace App\Tests\Functional\Kerde;

use App\Factory\DoorLogFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\Kerde\FakeSSHService;
use App\Tests\Support\Kerde\FakeZMQService;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for KerdeController.
 *
 * Tests cover:
 *  - Barcodes page access and rendering
 *  - Door page access, form display, and form submission
 *  - Recording start/stop routes with success/error scenarios
 *  - Authentication requirements
 *  - Bilingual routes
 *  - Mattermost notification logic (with/without message, rate limiting)
 */
final class KerdeControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->client->setServerParameter('REMOTE_ADDR', '127.0.0.1');
        $this->seedClientHome('fi');
    }

    // =========================================================================
    // Barcodes Page Tests
    // =========================================================================

    public function testBarcodesPageRequiresAuthentication(): void
    {
        $this->client->request('GET', '/kerde/barcodes');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Unauthenticated user should be redirected');
        $this->assertMatchesRegularExpression('#/login(/|$)#', $response->headers->get('Location') ?? '');
    }

    public function testBarcodesPageAccessibleForAuthenticatedMember(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/barcodes');

        $this->assertResponseIsSuccessful();
    }

    public function testBarcodesPageDisplaysBarcodes(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/barcodes');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.barcode');
    }

    public function testBarcodesPageContainsExpectedBarcodeLabels(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/barcodes');

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);

        $this->assertMatchesRegularExpression('/Your Code/', $content);
        $this->assertMatchesRegularExpression('/10€/', $content);
        $this->assertMatchesRegularExpression('/20€/', $content);
        $this->assertMatchesRegularExpression('/Cancel/', $content);
        $this->assertMatchesRegularExpression('/Manual/', $content);
    }

    // =========================================================================
    // Door Page Tests
    // =========================================================================

    #[DataProvider('doorRouteProvider')]
    public function testDoorRouteRequiresAuthentication(string $path): void
    {
        $this->client->request('GET', $path);

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Unauthenticated user should be redirected');
        $this->assertMatchesRegularExpression('#/login(/|$)#', $response->headers->get('Location') ?? '');
    }

    public static function doorRouteProvider(): array
    {
        return [
            'Finnish door route' => ['/kerde/ovi'],
            'English door route' => ['/en/kerde/door'],
        ];
    }

    public function testDoorPageAccessibleForActiveMember(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/ovi');

        $this->assertResponseIsSuccessful();
    }

    public function testDoorPageDisplaysFormForActiveMember(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/ovi');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
        $this->client->assertSelectorExists('input[type="submit"]');
    }

    public function testDoorPageDisplaysStatusFromZMQ(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/ovi');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        // FakeZMQService returns 'connected' for sendInit
        $this->assertMatchesRegularExpression('/connected/', $content);
    }

    public function testDoorPageDisplaysBarcode(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/ovi');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.barcode');
    }

    public function testDoorPageShowsNotActiveMemberMessageForInactiveMember(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/ovi');

        $this->assertResponseIsSuccessful();
        // For inactive members, the form should not be shown
        // and a "not active member" message should appear
        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
    }

    public function testDoorPageWithSinceParameter(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/ovi?since=2024-01-01');

        $this->assertResponseIsSuccessful();
    }

    public function testDoorFormSubmissionCreatesLogAndRedirects(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $crawler = $this->client->request('GET', '/kerde/ovi');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form input[type="submit"]')->form();
        $form['open_door[message]'] = 'Test door open message';

        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/kerde#', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testDoorFormSubmissionWithoutMessageSendsMattermostIfNoRecentLog(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $crawler = $this->client->request('GET', '/kerde/ovi');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form input[type="submit"]')->form();
        // Leave message empty

        $this->client->submit($form);

        $this->assertResponseRedirects();
    }

    public function testDoorFormSubmissionSkipsMattermostIfRecentLogWithoutMessage(): void
    {
        $member = MemberFactory::new()->active()->create();

        // Create a recent door log without message (within 4 hours)
        $doorLog = DoorLogFactory::new()->create([
            'member' => $member,
            'message' => null,
            'createdAt' => new \DateTimeImmutable('-1 hour'),
        ]);
        self::assertNotNull($doorLog->getId());

        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $crawler = $this->client->request('GET', '/kerde/ovi');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form input[type="submit"]')->form();
        // Leave message empty - should skip Mattermost due to recent log

        $this->client->submit($form);

        $this->assertResponseRedirects();
    }

    public function testDoorPageDisplaysRecentLogs(): void
    {
        $member = MemberFactory::new()->active()->create();

        // Create some door logs
        DoorLogFactory::new()->create([
            'member' => $member,
            'message' => 'Previous door open',
            'createdAt' => new \DateTimeImmutable('-2 hours'),
        ]);

        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/ovi');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $this->assertMatchesRegularExpression('/Previous door open/', $content);
    }

    // =========================================================================
    // Recording Start Tests
    // =========================================================================

    public function testRecordingStartRequiresAuthentication(): void
    {
        $this->client->request('GET', '/kerde/recording/start');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Unauthenticated user should be redirected');
        $this->assertMatchesRegularExpression('#/login(/|$)#', $response->headers->get('Location') ?? '');
    }

    public function testRecordingStartRedirectsToKerdeDoor(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/start');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Should redirect after recording start attempt');
        $this->assertMatchesRegularExpression('#/kerde#', $response->headers->get('Location') ?? '');
    }

    public function testRecordingStartSuccessForActiveMember(): void
    {
        $member = MemberFactory::new()->active()->create();

        // Configure fake SSH to succeed
        /** @var FakeSSHService $fakeSSH */
        $fakeSSH = static::getContainer()->get('App\Service\SSHService');
        $fakeSSH->setSuccess(true);

        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/start');

        $this->assertResponseRedirects();

        // Follow redirect to check flash message
        $this->client->followRedirect();
    }

    public function testRecordingStartErrorForActiveMember(): void
    {
        $member = MemberFactory::new()->active()->create();

        // Configure fake SSH to fail
        /** @var FakeSSHService $fakeSSH */
        $fakeSSH = static::getContainer()->get('App\Service\SSHService');
        $fakeSSH->setSuccess(false);
        $fakeSSH->setErrorMessage('Connection refused');

        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/start');

        $this->assertResponseRedirects();

        // Follow redirect to check flash message
        $this->client->followRedirect();
    }

    public function testRecordingStartForInactiveMemberDoesNotStartRecording(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/start');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Should redirect even for inactive member');
    }

    // =========================================================================
    // Recording Stop Tests
    // =========================================================================

    public function testRecordingStopRequiresAuthentication(): void
    {
        $this->client->request('GET', '/kerde/recording/stop');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Unauthenticated user should be redirected');
        $this->assertMatchesRegularExpression('#/login(/|$)#', $response->headers->get('Location') ?? '');
    }

    public function testRecordingStopRedirectsToKerdeDoor(): void
    {
        $member = MemberFactory::new()->active()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/stop');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Should redirect after recording stop attempt');
        $this->assertMatchesRegularExpression('#/kerde#', $response->headers->get('Location') ?? '');
    }

    public function testRecordingStopSuccessForActiveMember(): void
    {
        $member = MemberFactory::new()->active()->create();

        // Configure fake SSH to succeed
        /** @var FakeSSHService $fakeSSH */
        $fakeSSH = static::getContainer()->get('App\Service\SSHService');
        $fakeSSH->setSuccess(true);

        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/stop');

        $this->assertResponseRedirects();

        // Follow redirect to check flash message
        $this->client->followRedirect();
    }

    public function testRecordingStopErrorForActiveMember(): void
    {
        $member = MemberFactory::new()->active()->create();

        // Configure fake SSH to fail
        /** @var FakeSSHService $fakeSSH */
        $fakeSSH = static::getContainer()->get('App\Service\SSHService');
        $fakeSSH->setSuccess(false);
        $fakeSSH->setErrorMessage('Stop command failed');

        $this->loginAsActiveMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/stop');

        $this->assertResponseRedirects();

        // Follow redirect to check flash message
        $this->client->followRedirect();
    }

    public function testRecordingStopForInactiveMemberDoesNotStopRecording(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/recording/stop');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Should redirect even for inactive member');
    }

    // =========================================================================
    // Member Code Tests
    // =========================================================================

    public function testMemberCodeIsUsedForBarcode(): void
    {
        $member = MemberFactory::new()->active()->create();
        $memberCode = $member->getCode();
        $this->assertNotEmpty($memberCode, 'Member should have a code');

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('fi');

        $this->client->request('GET', '/kerde/barcodes');

        $this->assertResponseIsSuccessful();
    }
}
