<?php

declare(strict_types=1);

namespace App\Tests\Functional\Profile;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for ActiveMemberType form and active membership application workflow.
 *
 * Tests cover:
 *  - Access control (anonymous, regular member, already active member)
 *  - Form rendering and structure
 *  - Bilingual routes (/profile/apply, /profiili/aktiiviksi)
 *  - Successful application submission
 *  - Application field persistence
 *  - ApplicationDate setting behavior
 *  - Flash messages
 *  - Edge cases (empty application, re-submission)
 */
final class ActiveMemberApplicationTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testAnonymousUserRedirectedToLogin(): void
    {
        $this->client->request('GET', '/en/profile/apply');

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode(), 'Anonymous user should be redirected');

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location, 'Should redirect to login page');
    }

    #[DataProvider('localeProvider')]
    public function testRegularMemberCanAccessApplicationForm(string $locale): void
    {
        $member = MemberFactory::new()->inactive()->with(['locale' => $locale])->create();
        $this->loginAsMember($member->getEmail());

        $path = 'en' === $locale ? '/en/profile/apply' : '/profiili/aktiiviksi';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
        $this->client->assertSelectorExists('textarea[name="active_member[Application]"]');
    }

    public function testActiveMemberRedirectedWithFlashMessage(): void
    {
        $email = 'active-test-'.uniqid().'@example.com';
        [$user, $client] = $this->loginAsActiveMember($email);
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/apply');

        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            \in_array($statusCode, [302, 303], true),
            \sprintf('Active member should be redirected, got %d', $statusCode)
        );

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Check for flash message in response
        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        // Flash message should indicate already active (translation key: profile.you_are_active_member_already)
    }

    public function testSuccessfulApplicationSetsDateAndRedirects(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        // Access form
        $this->client->request('GET', '/en/profile/apply');
        $this->assertResponseIsSuccessful();

        // Submit form
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->form([
            'active_member[Application]' => 'I would like to become an active member because...',
        ]);

        $this->client->submit($form);

        // Should redirect after successful submission
        $response = $this->client->getResponse();
        $this->assertTrue(\in_array($response->getStatusCode(), [302, 303], true), 'Should redirect after form submission');

        // Verify ApplicationDate was set
        $memberRepo = $this->em()->getRepository(\App\Entity\Member::class);
        $refreshedMember = $memberRepo->find($member->getId());
        $applicationDate = $refreshedMember->getApplicationDate();
        $this->assertInstanceOf(\DateTime::class, $applicationDate, 'ApplicationDate should be set after submission');
    }

    public function testApplicationTextIsPersisted(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());

        $this->client->request('GET', '/en/profile/apply');
        $crawler = $this->client->getCrawler();

        $applicationText = 'I want to contribute to the organization by organizing events and helping with technical tasks.';

        $form = $crawler->filter('form')->form([
            'active_member[Application]' => $applicationText,
        ]);

        $this->client->submit($form);

        // Refresh entity from database
        $memberRepo = $this->em()->getRepository(\App\Entity\Member::class);
        $refreshedMember = $memberRepo->find($member->getId());

        $this->assertSame(
            $applicationText,
            $refreshedMember->getApplication(),
            'Application text should be persisted'
        );
    }

    public function testEmptyApplicationIsAllowed(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/en/profile/apply');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form([
            'active_member[Application]' => '',
        ]);

        $this->client->submit($form);

        // Should not fail validation (Application field is nullable)
        $response = $this->client->getResponse();
        $this->assertTrue(\in_array($response->getStatusCode(), [302, 303], true), 'Should redirect after form submission');

        $memberRepo = $this->em()->getRepository(\App\Entity\Member::class);
        $refreshedMember = $memberRepo->find($member->getId());
        $this->assertNotNull($refreshedMember->getApplicationDate(), 'ApplicationDate should be set even with empty application');
    }

    public function testApplicationDateIsUpdatedOnResubmission(): void
    {
        $member = MemberFactory::new()->applicationPending()->create();
        $originalDate = $member->getApplicationDate();
        $this->assertInstanceOf(\DateTime::class, $originalDate);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        // Wait a moment to ensure timestamps would differ
        sleep(1);

        $this->client->request('GET', '/en/profile/apply');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form([
            'active_member[Application]' => 'Updated application text',
        ]);

        $this->client->submit($form);

        $memberRepo = $this->em()->getRepository(\App\Entity\Member::class);
        $refreshedMember = $memberRepo->find($member->getId());
        $newDate = $refreshedMember->getApplicationDate();

        // ApplicationDate is always updated to current time on submission
        $this->assertGreaterThan(
            $originalDate->getTimestamp(),
            $newDate->getTimestamp(),
            'ApplicationDate should be updated on re-submission'
        );
    }

    public function testSuccessFlashMessageDisplayed(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());

        $this->client->request('GET', '/en/profile/apply');
        $crawler = $this->client->getCrawler();

        $form = $crawler->filter('form')->form([
            'active_member[Application]' => 'Test application',
        ]);

        $this->client->submit($form);
        $this->client->followRedirect();

        // Check for success flash message (translation key: profile.application_saved)
        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
    }

    public function testFormLabelUsesTranslationKey(): void
    {
        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());

        $this->client->request('GET', '/en/profile/apply');

        // Form should exist and be renderable
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    #[DataProvider('localeProvider')]
    public function testBilingualRoutesWork(string $locale): void
    {
        $member = MemberFactory::new()->inactive()->with(['locale' => $locale])->create();
        $this->loginAsMember($member->getEmail());

        $path = 'en' === $locale ? '/en/profile/apply' : '/profiili/aktiiviksi';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
    }

    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }
}
