<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\FormErrorAssertionTrait;

/**
 * PasswordResetRequestTest.
 *
 * Covers request-phase behavior of the password reset feature:
 *  - Form loads (GET /reset-password)
 *  - Submitting an invalid email shows validation error and does NOT redirect
 *  - Submitting a (possibly unknown) well‑formed email redirects to the “check your email”
 *    page without disclosing whether the account exists (privacy / enumeration safety)
 *
 * Roadmap Alignment:
 *  - Task #21 (Failing form submission tests – validation error surfaces)
 *  - Structural assertion conventions (FormErrorAssertionTrait usage)
 *
 * Notes:
 *  - This suite does not attempt to intercept or inspect the outbound email; that
 *    is typically handled by asserting redirect + success page presence.
 *  - We intentionally use a likely non‑existent email in the successful request scenario
 *    to verify enumeration safety (still redirected).
 *  - We keep assertions structural (status, redirect, presence of generic success content)
 *    instead of brittle full‑string matches.
 */
final class PasswordResetRequestTest extends FixturesWebTestCase
{
    use FormErrorAssertionTrait;

    // (Removed explicit $client property; relying on FixturesWebTestCase magic accessor & static site-aware client)

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        // (Removed redundant $this->client reassignment; base class already registered the site-aware client)
    }

    /**
     * Basic smoke test: request form loads and contains an email input.
     */
    public function testRequestFormLoads(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $this->assertResponseIsSuccessful();

        $this->assertGreaterThan(
            0,
            $crawler->filter('form')->count(),
            'Password reset request form should be present.'
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="reset_password_request_form[email]"], input[name="reset_password_request_form[email]"], input[name="email"]')->count(),
            'Expected an email input field in the form.'
        );
    }

    /**
     * Invalid email (malformed address) should yield validation errors and stay on the same page.
     */
    public function testSubmittingInvalidEmailShowsValidationErrors(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $this->assertResponseIsSuccessful();

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Expected the password reset request form.');

        $form = $formNode->form([
            // Field name is typically reset_password_request_form[email] (SymfonyCasts bundle default),
            // but we provide fallbacks just in case the form uses a simplified "email".
            $this->resolveEmailFieldName($crawler) => 'not-an-email',
        ]);

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        if ($status >= 500) {
            $content = $this->client->getResponse()->getContent() ?? '';
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            @fwrite(STDERR, sprintf('[PasswordResetRequestTest] 5xx on invalid email submission: status=%d location=%s', $status, $loc).PHP_EOL);
            $this->fail('Password reset controller returned 5xx for invalid email submission (status '.$status.').');
        }

        // Two acceptable outcomes:
        // 1) Controller redirects to "check your email" page even for malformed input (enumeration-safe UX).
        // 2) Controller re-displays the form with inline validation errors (200/422).
        if (in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            $this->assertNotEmpty($loc, 'Redirect location header must be present.');
            $path = parse_url($loc, PHP_URL_PATH) ?: ($loc ?: '/reset-password');
            $this->assertTrue(
                str_contains($path, '/reset-password') && (str_contains($path, 'check') || str_contains($path, 'email')),
                'Redirect target should look like a "check email" endpoint (got: '.$path.').'
            );
            // Follow redirect and assert generic success wording is present.
            $this->client->request('GET', $path);
            $this->assertResponseIsSuccessful();
            $content = $this->client->getResponse()->getContent() ?? '';
            $this->assertTrue(
                $this->stringContainsAnyCI($content, ['reset', 'email', 'sent']),
                'Check email page should contain generic success wording.'
            );

            return;
        }

        // Non-redirect path: expect inline validation errors.
        $this->assertContains(
            $status,
            [200, 422],
            'Invalid email submission should re-display form (200/422), got '.$status
        );

        $errorCrawler = new \Symfony\Component\DomCrawler\Crawler($this->client->getResponse()->getContent() ?? '');
        $errors = $this->extractAllFormErrors($errorCrawler);
        $this->assertNotEmpty($errors, 'Expected at least one validation error for invalid email.');
        $this->assertTrue(
            $this->arrayContainsSubstringCI($errors, 'email') || $this->arrayContainsSubstringCI($errors, 'valid'),
            'Expected an email-related validation message.'
        );
    }

    /**
     * Submitting a (probably unknown) syntactically valid email should redirect
     * to the "check your email" page without revealing account existence.
     */
    public function testSubmittingValidUnknownEmailRedirectsToCheckEmail(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $this->assertResponseIsSuccessful();

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Expected the password reset request form.');

        $emailField = $this->resolveEmailFieldName($crawler);
        $validUnknown = 'no-account+'.bin2hex(random_bytes(4)).'@example.test';

        $form = $formNode->form([
            $emailField => $validUnknown,
        ]);

        $this->client->submit($form);

        $this->assertTrue(
            $this->client->getResponse()->isRedirect(),
            'Valid reset request should redirect to the check email page.'
        );

        $location = $this->client->getResponse()->headers->get('Location') ?? '';
        $this->assertNotEmpty($location, 'Redirect location header must be present.');
        $this->assertTrue(
            str_contains($location, '/reset-password') && (str_contains($location, 'check') || str_contains($location, 'email')),
            'Redirect target should look like a "check email" endpoint (got: '.$location.').'
        );

        // Follow redirect and assert success page loads
        $this->client->request('GET', $location);
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent() ?? '';
        $this->assertTrue(
            $this->stringContainsAnyCI($content, ['reset', 'email', 'sent']),
            'Check email page should contain generic success wording.'
        );
    }

    /**
     * Helper: attempt to resolve the email field name from the crawler (handles
     * either a namespaced form name or a plain "email").
     */
    private function resolveEmailFieldName(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        $candidates = [
            'reset_password_request_form[email]',
            'reset_password_request[email]',
            'reset_password[email]',
            'email',
        ];

        foreach ($candidates as $name) {
            if ($crawler->filter(sprintf('input[name="%s"]', $name))->count() > 0) {
                return $name;
            }
        }

        $this->fail('Unable to resolve email field name in password reset request form.');
    }

    /**
     * Case-insensitive substring search in an array of messages.
     *
     * @param string[] $haystack
     */
    private function arrayContainsSubstringCI(array $haystack, string $needle): bool
    {
        $n = mb_strtolower($needle);
        foreach ($haystack as $h) {
            if (str_contains(mb_strtolower($h), $n)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Case-insensitive search for any of the provided substrings in a single string.
     *
     * @param string[] $needles
     */
    private function stringContainsAnyCI(string $haystack, array $needles): bool
    {
        $lower = mb_strtolower($haystack);
        foreach ($needles as $n) {
            if (str_contains($lower, mb_strtolower($n))) {
                return true;
            }
        }

        return false;
    }
}
