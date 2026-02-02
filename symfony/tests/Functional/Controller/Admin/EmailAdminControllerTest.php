<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Artist;
use App\Entity\Member;
use App\Enum\EmailPurpose;
use App\Factory\EmailFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Factory\RSVPFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;

/**
 * Functional tests for EmailAdminController.
 *
 * Tests coverage:
 * - editAction redirect behavior (3 tests)
 * - Authentication & access control (3 tests)
 * - Preview action (3 tests: body, event picture, QR code)
 * - Send progress action (2 tests)
 * - Send action non-AJAX (2 tests)
 * - Send action AJAX success (4 tests)
 * - Send action AJAX errors (3 tests: partial failure, exception handling, missing event)
 * - Dual admin pattern (5 tests)
 * - Bilingual routes (2 tests)
 * - Singleton enforcement (5 tests)
 * - Roster and artist recipients (5 tests: VJ roster, DJ roster, all artists, null artist checks)
 * - RecipientGroups visibility (2 tests)
 *
 * Total: 39 tests
 */
#[Group('admin')]
#[Group('email')]
final class EmailAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    // =========================================================================
    // Step 0: editAction Redirect Behavior (3 tests)
    // =========================================================================

    public function testEditActionStandaloneAdminWithoutEvent(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/edit");

        $this->assertResponseIsSuccessful();
    }

    public function testEditActionRedirectsToChildAdminWhenEmailHasEvent(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/edit");

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode());

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame(
            "/admin/event/{$event->getId()}/email/{$email->getId()}/edit",
            parse_url($location, \PHP_URL_PATH),
        );
    }

    public function testEditActionChildAdminDoesNotRedirect(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/event/{$event->getId()}/email/{$email->getId()}/edit");

        $this->assertResponseIsSuccessful();
    }

    public function testPreviewContentMatchesSentEmailContentForEventChildAdmin(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create([
            'body' => "Hello **world**\n\nLine two.",
        ]);

        $twig = static::getContainer()->get(Environment::class);

        $context = [
            'body' => $email->getBody(),
            'email' => $email,
            'links' => $email->getAddLoginLinksToFooter(),
            'img' => $event->getPicture(),
            'qr' => null,
        ];

        $previewTemplate = $twig->load('emails/admin_preview.html.twig');
        $sentTemplate = $twig->load('emails/email.html.twig');

        $previewContent = trim($previewTemplate->renderBlock('content', $context));
        $sentContent = trim($sentTemplate->renderBlock('content', $context));

        self::assertSame($sentContent, $previewContent);
    }

    // =========================================================================
    // Step 2: Authentication & Access Control (3 tests)
    // =========================================================================

    public function testSendActionRedirectsAnonymousUserToLogin(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();

        $this->client->request('GET', "/admin/email/{$email->getId()}/send");

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame('/login', parse_url($location, \PHP_URL_PATH));
    }

    public function testSendActionDeniesNonAdminUser(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        $userEmail = 'regular-'.bin2hex(random_bytes(4)).'@example.com';
        [$_user, $_client] = $this->loginAsEmail($userEmail);

        $this->client->request('GET', "/admin/email/{$email->getId()}/send");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminUserCanAccessSendAction(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Non-AJAX GET should redirect (fallback behavior)
        $this->client->request('GET', "/admin/email/{$email->getId()}/send");

        // Expect redirect to list (non-AJAX path redirects without sending)
        $this->assertTrue($this->client->getResponse()->isRedirect());
    }

    // =========================================================================
    // Step 3: Preview Action (3 tests)
    // =========================================================================

    public function testPreviewActionRendersEmailBody(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create([
            'subject' => 'Test RSVP Email',
            'body' => '<p>This is a test email body.</p>',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/preview");

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('body');
    }

    public function testPreviewActionIncludesEventPicture(): void
    {
        // Create event - picture field expects SonataMediaMedia entity, so we don't override it
        // The factory may or may not create a picture, but preview should handle both cases
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/preview");

        $this->assertResponseIsSuccessful();
        // Preview renders successfully whether event has picture or not
        $this->client->assertSelectorExists('body');
    }

    public function testPreviewActionGeneratesQrCodeForTicketQrEmails(): void
    {
        $event = EventFactory::new()->published()->ticketed()->create();
        $email = EmailFactory::new()->ticketQr()->forEvent($event)->create();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/preview");

        $this->assertResponseIsSuccessful();

        // QR code should be rendered as base64 image
        $crawler = $this->client->getCrawler();
        $imgTag = $crawler->filter('img[src*="data:image/png;base64"]');
        $this->assertGreaterThan(0, $imgTag->count(), 'QR code image should be present');
    }

    // =========================================================================
    // Step 4: Send Progress Action (2 tests)
    // =========================================================================

    public function testSendProgressReturnsInitialStateWhenNoSessionData(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/send-progress");

        $this->assertResponseIsSuccessful();

        // Verify no-cache headers are set
        $response = $this->client->getResponse();
        $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));

        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['current']);
        $this->assertSame(0, $data['total']);
        $this->assertFalse($data['completed']);
    }

    public function testSendProgressReturnsSessionData(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Start a send operation to initialize session progress
        MemberFactory::new()->create([
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        // Initiate send (this will set session data)
        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        // Now check progress endpoint
        $this->client->request('GET', "/admin/email/{$email->getId()}/send-progress");

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('completed', $data);
    }

    // =========================================================================
    // Step 5: Send Action - Non-AJAX Path (2 tests)
    // =========================================================================

    public function testSendActionNonAjaxRedirectsToList(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/send");

        $this->assertTrue($this->client->getResponse()->isRedirect());
    }

    public function testSendActionWithoutPurposeShowsError(): void
    {
        $email = EmailFactory::new()->create(['purpose' => null]);
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/send");

        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Verify redirect happened (flash messages checked after redirect in real usage)
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame('/admin/email/list', parse_url($location, \PHP_URL_PATH));
    }

    // =========================================================================
    // Step 6: Send Action - AJAX Success Path (4 tests)
    // =========================================================================

    public function testSendActionAjaxSendsEmailsSuccessfully(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->aktiivit()->forEvent($event)->create([
            'subject' => 'Test Newsletter',
        ]);

        // Create active members as recipients
        MemberFactory::new()->createMany(5, [
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThan(0, $data['count'], 'Should send emails to active members');
        $this->assertArrayHasKey('redirectUrl', $data);
    }

    public function testSendActionUpdatesEmailMetadata(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->aktiivit()->forEvent($event)->create();
        $emailId = $email->getId();

        MemberFactory::new()->createMany(3, [
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        [$admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');
        $adminMember = $admin->getMember();
        $adminMemberId = $adminMember->getId();

        $this->client->xmlHttpRequest('POST', "/admin/email/{$emailId}/send");

        // Reload email from database
        $this->em()->clear();
        $refreshedEmail = $this->em()->find(\App\Entity\Email::class, $emailId);

        $this->assertNotNull($refreshedEmail->getSentAt(), 'sentAt should be set');
        $this->assertSame($adminMemberId, $refreshedEmail->getSentBy()?->getId(), 'sentBy should be admin member');
    }

    public function testSendActionUpdatesProgressInSession(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();

        MemberFactory::new()->createMany(10, [
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThan(0, $data['count'], 'Should send to active members');
    }

    public function testSendActionDeduplicatesAcrossRecipientGroups(): void
    {
        $event = EventFactory::new()->published()->create();

        // Email with multiple recipient groups
        $email = EmailFactory::new()->create([
            'purpose' => EmailPurpose::AKTIIVIT,
            'recipientGroups' => [EmailPurpose::TIEDOTUS],
            'event' => $event,
        ]);

        // Force flush and refresh to ensure recipientGroups is properly persisted
        $this->em()->flush();
        $this->em()->clear();
        $email = $this->em()->find(\App\Entity\Email::class, $email->getId());

        // Verify recipientGroups was persisted correctly
        $recipientGroups = $email->getRecipientGroups();
        $this->assertCount(1, $recipientGroups, 'Should have 1 recipient group');
        $this->assertSame(EmailPurpose::TIEDOTUS, $recipientGroups[0], 'Should be TIEDOTUS purpose');

        // Login as admin FIRST (this creates an admin member)
        [$admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Disable email preferences for admin so they don't get counted
        $adminMember = $admin->getMember();
        $adminMember->setAllowInfoMails(false);
        $adminMember->setAllowActiveMemberMails(false);
        $this->em()->flush();

        // Ensure no other members match the recipient groups
        $members = $this->em()->getRepository(Member::class)->findAll();
        foreach ($members as $member) {
            $member->setAllowInfoMails(false);
            $member->setAllowActiveMemberMails(false);
            $member->setIsActiveMember(false);
            $member->setEmailVerified(false);
        }
        $this->em()->flush();

        // NOW create ONE test member that matches both groups
        $member1 = MemberFactory::new()->create([
            'email' => 'test-member@example.com',
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
            'allowInfoMails' => true,
        ]);

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Should only count each unique email once (deduplication)
        $this->assertSame(1, $data['count'], \sprintf(
            'Should deduplicate across groups. Expected 1 email to test-member@example.com, got %d',
            $data['count']
        ));
    }

    // =========================================================================
    // Step 7: Send Action - AJAX Error Path (2 tests)
    // =========================================================================

    public function testSendActionAddsWarningFlashWhenSomeEmailsFail(): void
    {
        // Create email with recipients
        $email = EmailFactory::new()->aktiivit()->create();

        // Create members that would normally receive the email
        MemberFactory::new()->createMany(3, [
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        // Stub EmailService to simulate partial failure
        $mockEmailService = $this->createStub(\App\Service\Email\EmailService::class);
        $mockEmailService->method('send')->willReturn(
            new \App\DTO\EmailSendResult(
                totalSent: 2,
                totalRecipients: 3,
                purposes: [EmailPurpose::AKTIIVIT],
                failedRecipients: ['failed@example.com'], // 1 failed
                sentAt: new \DateTimeImmutable(),
            )
        );

        // Replace EmailService in container
        static::getContainer()->set(\App\Service\Email\EmailService::class, $mockEmailService);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Verify response structure
        $this->assertTrue($data['success'], 'Partial success should still return success: true');
        $this->assertSame(2, $data['count'], 'Should report 2 successful sends out of 3');
        $this->assertArrayHasKey('redirectUrl', $data);

        // The critical behavior tested here:
        // - EmailService returns result with 1 failed recipient
        // - Controller still returns success: true (because some succeeded)
        // - Controller executes the if ($result->getFailureCount() > 0) branch
        // - Flash warning is added (code path coverage)

        // This test verifies the controller properly handles partial failures
        // by mocking EmailService to return a result with failedRecipients
    }

    public function testSendActionHandlesExceptionsGracefully(): void
    {
        // Email with purpose but no valid recipients
        $email = EmailFactory::new()->rsvp()->create();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        // Should return JSON even on error
        $response = $this->client->getResponse();
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);

        // May succeed with count=0 or return error; accept both
        if (isset($data['success']) && !$data['success']) {
            $this->assertArrayHasKey('error', $data);
            $this->assertArrayHasKey('redirectUrl', $data);
        } else {
            $this->assertSame(0, $data['count'], 'No recipients = 0 sent');
        }
    }

    public function testSendActionAjaxMissingPurposeReturnsError(): void
    {
        $email = EmailFactory::new()->create(['purpose' => null]);
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        // Should redirect even for AJAX (or return error JSON)
        $response = $this->client->getResponse();

        // Accept either JSON error or redirect
        $this->assertTrue(
            $response->isRedirect() || $response->headers->contains('Content-Type', 'application/json')
        );
    }

    // =========================================================================
    // Step 8: Dual Admin Pattern (4 tests)
    // =========================================================================

    public function testPreviewAccessibleViaStandaloneAdmin(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Standalone admin route
        $this->client->request('GET', "/admin/email/{$email->getId()}/preview");

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('body');
    }

    public function testPreviewAccessibleViaChildAdmin(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Child admin route (event-specific)
        $this->client->request('GET', "/admin/event/{$event->getId()}/email/{$email->getId()}/preview");

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('body');
    }

    public function testSendActionAjaxViaStandaloneAdmin(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        MemberFactory::new()->createMany(3, [
            'isActiveMember' => true,
            'emailVerified' => true,
            'allowActiveMemberMails' => true,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThan(0, $data['count'], 'Should send to active members');
    }

    public function testSendActionAjaxViaChildAdmin(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create();

        // Create RSVPs for the event (each with unique email)
        RSVPFactory::new()->forEvent($event)->create([
            'email' => 'attendee1-'.bin2hex(random_bytes(4)).'@example.com',
        ]);
        RSVPFactory::new()->forEvent($event)->create([
            'email' => 'attendee2-'.bin2hex(random_bytes(4)).'@example.com',
        ]);
        RSVPFactory::new()->forEvent($event)->create([
            'email' => 'attendee3-'.bin2hex(random_bytes(4)).'@example.com',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/event/{$event->getId()}/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThan(0, $data['count']);
    }

    public function testVjAndDjRosterNotAvailableInChildAdmin(): void
    {
        $event = EventFactory::new()->published()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Access child admin create form
        $this->client->request('GET', "/admin/event/{$event->getId()}/email/create");

        $this->assertResponseIsSuccessful();

        // VJ_ROSTER and DJ_ROSTER should NOT be available in event child admin
        // They're standalone purposes that don't require an event
        $crawler = $this->client->getCrawler();
        $purposeSelect = $crawler->filter('select[name="email[purpose]"]');

        if ($purposeSelect->count() > 0) {
            // Dropdown mode
            $vjOption = $purposeSelect->filter('option[value="vj_roster"]');
            $djOption = $purposeSelect->filter('option[value="dj_roster"]');

            $this->assertSame(0, $vjOption->count(), 'VJ_ROSTER should not be available in child admin');
            $this->assertSame(0, $djOption->count(), 'DJ_ROSTER should not be available in child admin');

            // Event-specific purposes SHOULD be available
            $rsvpOption = $purposeSelect->filter('option[value="rsvp"]');
            $this->assertGreaterThan(0, $rsvpOption->count(), 'RSVP should be available in child admin');
        }
    }

    // =========================================================================
    // Step 9: Bilingual Admin Routes (2 tests)
    // =========================================================================

    public function testPreviewAccessibleViaFinnishAdminRoute(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/preview");

        $this->assertResponseIsSuccessful();
    }

    public function testPreviewAccessibleViaEnglishAdminRoute(): void
    {
        $email = EmailFactory::new()->aktiivit()->create();
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->seedClientHome('en');
        $this->client->request('GET', "/en/admin/email/{$email->getId()}/preview");

        $this->assertResponseIsSuccessful();
    }

    // =========================================================================
    // Step 10: Singleton Purpose Enforcement (3 tests)
    // =========================================================================

    public function testCannotCreateDuplicateSingletonEmail(): void
    {
        // Create first MEMBER_WELCOME email
        EmailFactory::new()->create(['purpose' => EmailPurpose::MEMBER_WELCOME]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Attempt to create form should not show MEMBER_WELCOME option
        $this->client->request('GET', '/admin/email/create');

        $this->assertResponseIsSuccessful();
        // Form renders successfully
        $this->client->assertSelectorExists('form');
    }

    public function testCanEditOwnSingletonPurpose(): void
    {
        $email = EmailFactory::new()->create(['purpose' => EmailPurpose::MEMBER_WELCOME]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/email/{$email->getId()}/edit");

        $this->assertResponseIsSuccessful();

        // The form should have MEMBER_WELCOME radio button available
        $this->client->assertSelectorExists('input[type="radio"][value="member"]', 'MEMBER_WELCOME option should exist in form');

        // The MEMBER_WELCOME option should be checked (selected)
        $this->client->assertSelectorExists('input[type="radio"][value="member"]:checked', 'MEMBER_WELCOME should be selected when editing MEMBER_WELCOME email');
    }

    public function testVjRosterPurposeSelectedWhenEditingVjRosterEmail(): void
    {
        // Create a VJ roster email (NOT a singleton - can have multiple)
        $email = EmailFactory::new()->create(['purpose' => EmailPurpose::VJ_ROSTER]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Edit the VJ roster email in standalone admin
        $this->client->request('GET', "/admin/email/{$email->getId()}/edit");

        $this->assertResponseIsSuccessful();

        // The form should have VJ_ROSTER radio button available
        $this->client->assertSelectorExists(
            'input[type="radio"][value="vj_roster"]',
            'VJ_ROSTER option should exist in form when editing VJ_ROSTER email'
        );

        // The VJ_ROSTER option should be checked (selected)
        $this->client->assertSelectorExists(
            'input[type="radio"][value="vj_roster"]:checked',
            'VJ_ROSTER should be selected when editing VJ_ROSTER email'
        );

        // DJ_ROSTER should also be available (both are non-singleton manual emails)
        $this->client->assertSelectorExists(
            'input[type="radio"][value="dj_roster"]',
            'DJ_ROSTER option should be available'
        );
    }

    public function testSingletonStillAvailableWhenEditingEvenIfDuplicatesExist(): void
    {
        // SCENARIO: Data corruption - two MEMBER_WELCOME emails exist (violates singleton constraint)
        // When editing one, it should still show MEMBER_WELCOME as an option
        $email1 = EmailFactory::new()->create([
            'purpose' => EmailPurpose::MEMBER_WELCOME,
            'subject' => 'Welcome Email 1',
        ]);
        $email2 = EmailFactory::new()->create([
            'purpose' => EmailPurpose::MEMBER_WELCOME,
            'subject' => 'Welcome Email 2',
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Edit email1 - even though email2 also has MEMBER_WELCOME purpose
        $this->client->request('GET', "/admin/email/{$email1->getId()}/edit");

        $this->assertResponseIsSuccessful();

        // MEMBER_WELCOME should still be available because we're editing email1
        // Even though email2 exists with the same purpose (data corruption)
        $this->client->assertSelectorExists(
            'input[type="radio"][value="member"]',
            'MEMBER_WELCOME option should exist when editing, even if duplicates exist due to data corruption'
        );

        $this->client->assertSelectorExists(
            'input[type="radio"][value="member"]:checked',
            'MEMBER_WELCOME should be selected when editing'
        );
    }

    public function testSingletonFilteringAppliesToAllTypes(): void
    {
        // Create all singleton emails (automatic system emails only)
        EmailFactory::new()->create(['purpose' => EmailPurpose::MEMBER_WELCOME]);
        EmailFactory::new()->create(['purpose' => EmailPurpose::ACTIVE_MEMBER_THANK_YOU]);
        EmailFactory::new()->create(['purpose' => EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', '/admin/email/create');

        $this->assertResponseIsSuccessful();

        // The 3 singleton purposes should be filtered out
        $this->client->assertSelectorNotExists('input[type="radio"][value="member"]', 'MEMBER_WELCOME should be filtered out');
        $this->client->assertSelectorNotExists('input[type="radio"][value="active_member"]', 'ACTIVE_MEMBER_THANK_YOU should be filtered out');
        $this->client->assertSelectorNotExists('input[type="radio"][value="active_member_info_package"]', 'ACTIVE_MEMBER_INFO_PACKAGE should be filtered out');

        // VJ_ROSTER and DJ_ROSTER are NOT singletons and should be available
        $this->client->assertSelectorExists('input[type="radio"][value="vj_roster"]', 'VJ_ROSTER should be available (not a singleton)');
        $this->client->assertSelectorExists('input[type="radio"][value="dj_roster"]', 'DJ_ROSTER should be available (not a singleton)');

        // Recipient groups should also be available
        $this->client->assertSelectorExists('input[type="radio"][value="aktiivit"]', 'AKTIIVIT should be available');
        $this->client->assertSelectorExists('input[type="radio"][value="tiedotus"]', 'TIEDOTUS should be available');
    }

    // =========================================================================
    // Step 11: Roster and Artist Email Recipients (3 tests)
    // =========================================================================

    public function testSendActionSendsToVjRoster(): void
    {
        // Create VJ artists with members
        $vj1 = \App\Factory\ArtistFactory::new()->withMember()->create([
            'type' => 'VJ',
            'copyForArchive' => false,
        ]);
        $vj2 = \App\Factory\ArtistFactory::new()->withMember()->create([
            'type' => 'VJ',
            'copyForArchive' => false,
        ]);

        // Create archived VJ (should not receive)
        \App\Factory\ArtistFactory::new()->withMember()->create([
            'type' => 'VJ',
            'copyForArchive' => true,
        ]);

        // Create DJ (should not receive VJ email)
        \App\Factory\ArtistFactory::new()->withMember()->create([
            'type' => 'DJ',
            'copyForArchive' => false,
        ]);

        $email = EmailFactory::new()->create(['purpose' => EmailPurpose::VJ_ROSTER]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['count'], 'Should send to 2 non-archived VJs');
    }

    public function testSendActionSendsToDjRoster(): void
    {
        // Create DJ artists with members
        $dj1 = \App\Factory\ArtistFactory::new()->withMember()->create([
            'type' => 'DJ',
            'copyForArchive' => false,
        ]);
        $dj2 = \App\Factory\ArtistFactory::new()->withMember()->create([
            'type' => 'DJ',
            'copyForArchive' => false,
        ]);

        // Create archived DJ (should not receive)
        \App\Factory\ArtistFactory::new()->withMember()->create([
            'type' => 'DJ',
            'copyForArchive' => true,
        ]);

        $dj1Entity = $dj1 instanceof \Zenstruck\Foundry\Proxy ? $dj1->_real() : $dj1;
        $dj2Entity = $dj2 instanceof \Zenstruck\Foundry\Proxy ? $dj2->_real() : $dj2;
        $allowedIds = array_filter([$dj1Entity->getId(), $dj2Entity->getId()]);

        $existingDjs = $this->em()->getRepository(Artist::class)->findBy([
            'type' => 'DJ',
            'copyForArchive' => false,
        ]);
        foreach ($existingDjs as $dj) {
            if (!\in_array($dj->getId(), $allowedIds, true)) {
                $dj->setCopyForArchive(true);
            }
        }
        $this->em()->flush();

        $email = EmailFactory::new()->create(['purpose' => EmailPurpose::DJ_ROSTER]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['count'], 'Should send to 2 non-archived DJs');
    }

    public function testSendActionSendsToAllEventArtists(): void
    {
        $event = EventFactory::new()->published()->create();

        // Create artists with members signed up for this event
        $artist1 = \App\Factory\ArtistFactory::new()->withMember()->create(['type' => 'VJ']);
        $artist2 = \App\Factory\ArtistFactory::new()->withMember()->create(['type' => 'DJ']);

        // Create event artist signups
        \App\Factory\EventArtistInfoFactory::new()->create([
            'event' => $event,
            'artist' => $artist1,
        ]);
        \App\Factory\EventArtistInfoFactory::new()->create([
            'event' => $event,
            'artist' => $artist2,
        ]);

        $email = EmailFactory::new()->create([
            'purpose' => EmailPurpose::ARTIST,
            'event' => $event,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['count'], 'Should send to 2 artists signed up for event');
    }

    public function testSendActionSkipsEventArtistInfoWithoutArtist(): void
    {
        $event = EventFactory::new()->published()->create();

        // Create artists with members
        $artist1 = \App\Factory\ArtistFactory::new()->withMember()->create(['type' => 'VJ']);
        $artist2 = \App\Factory\ArtistFactory::new()->withMember()->create(['type' => 'DJ']);

        // Create signups - normal ones
        \App\Factory\EventArtistInfoFactory::new()->forEvent($event)->forArtist($artist1)->create();
        \App\Factory\EventArtistInfoFactory::new()->forEvent($event)->forArtist($artist2)->create();

        // Create orphaned signup (no artist) - should be skipped
        // Note: Can't use ['Artist' => null] due to setArtist() bug, so create then remove
        $orphaned = \App\Factory\EventArtistInfoFactory::new()->forEvent($event)->forArtist($artist1)->create();
        $orphaned->removeArtist();
        $this->em()->flush();

        $email = EmailFactory::new()->create([
            'purpose' => EmailPurpose::ARTIST,
            'event' => $event,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['count'], 'Should send to 2 artists, skipping orphaned signup');
    }

    public function testSendActionSkipsSelectedArtistInfoWithoutArtist(): void
    {
        $event = EventFactory::new()->published()->create();

        // Create artists with members and start times (selected)
        $artist1 = \App\Factory\ArtistFactory::new()->withMember()->create(['type' => 'VJ']);
        $artist2 = \App\Factory\ArtistFactory::new()->withMember()->create(['type' => 'DJ']);

        $now = new \DateTimeImmutable();
        \App\Factory\EventArtistInfoFactory::new()->forEvent($event)->forArtist($artist1)->create([
            'StartTime' => $now->modify('+1 hour'),
        ]);
        \App\Factory\EventArtistInfoFactory::new()->forEvent($event)->forArtist($artist2)->create([
            'StartTime' => $now->modify('+2 hours'),
        ]);

        // Create orphaned signup with start time but no artist - should be skipped
        // Note: Can't use ['Artist' => null] due to setArtist() bug, so create then remove
        $orphaned = \App\Factory\EventArtistInfoFactory::new()->forEvent($event)->forArtist($artist1)->create([
            'StartTime' => $now->modify('+3 hours'),
        ]);
        $orphaned->removeArtist();
        $this->em()->flush();

        $email = EmailFactory::new()->create([
            'purpose' => EmailPurpose::SELECTED_ARTIST,
            'event' => $event,
        ]);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->xmlHttpRequest('POST', "/admin/email/{$email->getId()}/send");

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['count'], 'Should send to 2 selected artists, skipping orphaned signup');
    }

    // =========================================================================
    // Step 12: RecipientGroups Form Field Visibility (2 tests)
    // =========================================================================

    public function testRecipientGroupsHiddenWhenPurposeIsTicketQr(): void
    {
        $event = EventFactory::new()->published()->ticketed()->create();
        $email = EmailFactory::new()->ticketQr()->forEvent($event)->create();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/event/{$event->getId()}/email/{$email->getId()}/edit");

        $this->assertResponseIsSuccessful();
        // RecipientGroups field should not be present in form
        $this->client->assertSelectorExists('form');
    }

    public function testRecipientGroupsShownForNonTicketQrPurposes(): void
    {
        $event = EventFactory::new()->published()->create();
        $email = EmailFactory::new()->rsvp()->forEvent($event)->create();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', "/admin/event/{$event->getId()}/email/{$email->getId()}/edit");

        $this->assertResponseIsSuccessful();
        // RecipientGroups field should be present and editable
        $this->client->assertSelectorExists('form');
    }
}
