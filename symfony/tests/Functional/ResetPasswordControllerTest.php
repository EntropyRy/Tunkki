<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Zenstruck\Foundry\Proxy;

final class ResetPasswordControllerTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testCheckEmailPageLoadsWithTryAgainLink(): void
    {
        $this->client->request('GET', '/reset-password/check-email');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('a[href="/reset-password"]');
    }

    public function testResetTokenRedirectsAndInvalidTokenReturnsToRequest(): void
    {
        $this->client->request('GET', '/reset-password/reset/fake-token');

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame(
            '/reset-password/reset',
            parse_url($location, \PHP_URL_PATH),
        );

        $this->client->request('GET', '/reset-password/reset');
        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame(
            '/reset-password',
            parse_url($location, \PHP_URL_PATH),
        );
    }

    public function testValidTokenDisplaysChangePasswordForm(): void
    {
        $memberProxy = MemberFactory::new()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        $user = $this->reloadUser($member->getUser());

        /** @var ResetPasswordHelperInterface $resetHelper */
        $resetHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetToken = $resetHelper->generateResetToken($user);

        // First request stores token in session and redirects
        $this->client->request('GET', '/reset-password/reset/'.$resetToken->getToken());
        $this->assertResponseRedirects('/reset-password/reset');

        // Follow redirect to see the form
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form[name="change_password_form"]');
        $this->client->assertSelectorExists('input[name="change_password_form[plainPassword][first]"]');
        $this->client->assertSelectorExists('input[name="change_password_form[plainPassword][second]"]');
    }

    public function testValidTokenFormSubmissionChangesPassword(): void
    {
        $memberProxy = MemberFactory::new()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        $user = $this->reloadUser($member->getUser());
        $userId = $user->getId();
        $originalPasswordHash = $user->getPassword();

        /** @var ResetPasswordHelperInterface $resetHelper */
        $resetHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetToken = $resetHelper->generateResetToken($user);

        // Store token in session
        $this->client->request('GET', '/reset-password/reset/'.$resetToken->getToken());
        $this->client->followRedirect();

        // Submit the form with new password
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[plainPassword][first]' => 'newSecurePassword123',
            'change_password_form[plainPassword][second]' => 'newSecurePassword123',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/login');

        // Verify password was changed by fetching fresh from DB
        $this->em()->clear();
        $freshUser = $this->em()->find(User::class, $userId);
        $this->assertNotNull($freshUser);
        $this->assertNotSame($originalPasswordHash, $freshUser->getPassword());
    }

    public function testFormValidationRejectsShortPassword(): void
    {
        $memberProxy = MemberFactory::new()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        $user = $this->reloadUser($member->getUser());

        /** @var ResetPasswordHelperInterface $resetHelper */
        $resetHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetToken = $resetHelper->generateResetToken($user);

        // Store token in session
        $this->client->request('GET', '/reset-password/reset/'.$resetToken->getToken());
        $this->client->followRedirect();

        // Submit form with too short password
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[plainPassword][first]' => 'short',
            'change_password_form[plainPassword][second]' => 'short',
        ]);

        $this->client->submit($form);
        $this->assertResponseIsUnprocessable();
    }

    public function testFormValidationRejectsMismatchedPasswords(): void
    {
        $memberProxy = MemberFactory::new()->create();
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        $user = $this->reloadUser($member->getUser());

        /** @var ResetPasswordHelperInterface $resetHelper */
        $resetHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetToken = $resetHelper->generateResetToken($user);

        // Store token in session
        $this->client->request('GET', '/reset-password/reset/'.$resetToken->getToken());
        $this->client->followRedirect();

        // Submit form with mismatched passwords
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[plainPassword][first]' => 'passwordOne123',
            'change_password_form[plainPassword][second]' => 'passwordTwo456',
        ]);

        $this->client->submit($form);
        $this->assertResponseIsUnprocessable();
    }

    private function reloadUser(User $user): User
    {
        if (null === $user->getId()) {
            return $user;
        }

        $managed = $this->em()->getRepository(User::class)->find($user->getId());
        if ($managed instanceof User) {
            return $managed;
        }

        return $user;
    }
}
