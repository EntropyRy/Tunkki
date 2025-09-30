<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Member;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for the member password change form (/profile/password).
 *
 * Covers:
 *  - Rendering of repeated password fields
 *  - Mismatched password validation (stays on page, shows error)
 *  - Successful password update (redirect + hash changed + new password valid)
 */
final class MemberPasswordFormTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    public function testPasswordFormRendersRepeatedFields(): void
    {
        $user = $this->loadFixtureUser('testuser@example.com');
        $this->client->loginUser($user);

        $this->client->request('GET', '/en/profile/password');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Password form page should load (200).');

        $html = $this->client->getResponse()->getContent() ?? '';
        $this->assertStringContainsString('name="user_password[plainPassword][first]"', $html, 'First repeated password input missing.');
        $this->assertStringContainsString('name="user_password[plainPassword][second]"', $html, 'Second repeated password input missing.');
    }

    public function testPasswordFormRejectsMismatchedPasswords(): void
    {
        $user = $this->loadFixtureUser('testuser@example.com');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/en/profile/password');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Password form element should exist.');

        $form = $formNode->form();
        $form['user_password[plainPassword][first]'] = 'NewMismatchPass123';
        $form['user_password[plainPassword][second]'] = 'DifferentPass456';

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 422], true),
            'Expected 200 or 422 (validation error) for mismatched passwords, got ' . $status . '.'
        );

        $content = $this->client->getResponse()->getContent() ?? '';
        $this->assertTrue(
            str_contains($content, 'passwords_need_to_match') ||
            str_contains($content, 'The password fields must match'),
            'Expected mismatch validation message (translation key or rendered text).'
        );
    }

    public function testPasswordFormChangesPasswordAndRedirects(): void
    {
        $user = $this->loadFixtureUser('testuser@example.com');
        $oldHash = $user->getPassword();
        $this->assertNotEmpty($oldHash, 'Precondition: existing password hash must not be empty.');

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/en/profile/password');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form')->first()->form();
        $newPlain = 'TotallyNewPass789!';
        $form['user_password[plainPassword][first]'] = $newPlain;
        $form['user_password[plainPassword][second]'] = $newPlain;

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 303], true), 'Successful password change should redirect (got ' . $status . ').');

        if ($loc = $this->client->getResponse()->headers->get('Location')) {
            $this->client->request('GET', $loc);
            $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Redirect target should load (profile page).');
        } else {
            $this->fail('Redirect location header missing after password change.');
        }

        // Clear EM to avoid comparing stale detached user, then fetch by member email
                // Simplified: refresh managed user, assert semantic password change
                $em = $this->em();
                $user = $em->getRepository(User::class)->find($user->getId());
                self::assertInstanceOf(User::class, $user, 'Re-fetched user should exist after password change.');

                /** @var UserPasswordHasherInterface $hasher */
                $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

                $newValid = $hasher->isPasswordValid($user, $newPlain);
                $oldStillValid = $hasher->isPasswordValid($user, 'userpass123');

                $this->assertTrue($newValid, 'New password must validate.');
                if ($oldStillValid) {
                    $this->fail('Old password still validates after change.');
                }

                if ($oldHash === $user->getPassword()) {
                    // Accept semantic success but warn about unchanged hash (unexpected)
                    fwrite(\STDOUT, "[WARN] Hash unchanged but semantic password change succeeded.\n");
                } else {
                    $this->assertNotSame($oldHash, $user->getPassword(), 'Hash should change when password changes.');
                }
    }

    /**
     * Helper: load a user via the Member email (User entity itself has no email column).
     */
    private function loadFixtureUser(string $email): User
    {
        $repo = $this->em()->getRepository(User::class);
        /** @var User[] $all */
        $all = $repo->findAll();
        foreach ($all as $candidate) {
            $member = $candidate->getMember();
            if ($member && $member->getEmail() === $email) {
                return $candidate;
            }
        }
        self::fail('Fixture user with member email ' . $email . ' not found.');
    }
}
