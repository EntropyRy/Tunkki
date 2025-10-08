<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * LocaleDataProviderTrait.
 *
 * Provides reusable data providers and helper utilities for locale–aware
 * functional tests (profile editing, localized navigation, etc.).
 *
 * Goals:
 *  - Centralize locale dataset definitions (avoid duplicating arrays across tests)
 *  - Offer semantic metadata per locale (edit path, expected post-save profile path)
 *  - Keep tests focused on assertions instead of mechanical array setup
 *
 * Usage Example (New Test):
 *
 *  use App\Tests\Support\LocaleDataProviderTrait;
 *  use App\Tests\Support\LoginHelperTrait;
 *  use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
 *
 *  final class ProfileEditLocaleTest extends WebTestCase
 *  {
 *      use LocaleDataProviderTrait;
 *      use LoginHelperTrait;
 *      /**
 *       * @dataProvider provideProfileEditLocales
 *       *\/
 *      public function testProfileEditLoadsForLocale(
 *          string $locale,
 *          string $editPath,
 *          string $expectedProfileFragment
 *      ): void {
 *          // Arrange
 *          [$user, $client] = $this->loginAsEmail(
 *              sprintf('locale-dp+%s@example.test', bin2hex(random_bytes(3)))
 *          );
 *
 *          // Act: request the locale-specific edit page
 *          $client->request('GET', $editPath);
 *
 *          // Assert: page & form present
 *          self::assertResponseIsSuccessful();
 *          self::assertSelectorExists('form');
 *
 *          // Submit without changes (idempotent round-trip) if needed:
 *          // $form = $client->getCrawler()->filter('form')->first()->form();
 *          // $client->submit($form);
 *          // self::assertResponseStatusCodeSame(302);
 *          // $redirect = $client->getResponse()->headers->get('Location') ?? '';
 *          // self::assertStringContainsString($expectedProfileFragment, $redirect);
 *      }
 *  }
 *
 * Adjust the FI_* paths below if your routing differs. If the Finnish edit
 * route requires a locale prefix (e.g. /fi/...), update FI_EDIT_PATH accordingly.
 */
trait LocaleDataProviderTrait
{
    /**
     * Canonical English edit path.
     * Matches current usage in MemberFormTypeTest.
     */
    public const EN_EDIT_PATH = '/en/profile/edit';

    /**
     * Finnish profile base path as asserted in existing tests (redirect target).
     * MemberFormTypeTest expects '/profiili' after locale switch.
     */
    public const FI_PROFILE_PATH = '/profiili';

    /**
     * Finnish profile edit path (GUESS).
     *
     * If your actual route differs (e.g. '/profiili/muokkaa' or '/fi/profile/edit'),
     * adjust this constant and any tests that consume it.
     */
    public const FI_EDIT_PATH = '/profiili/muokkaa';

    /**
     * Provide locale -> (locale code, edit path, expected profile path fragment).
     *
     * Each element:
     *  [
     *     0 => locale code (string),
     *     1 => edit path (string),
     *     2 => expected profile LOCATION fragment after a successful save/redirect
     *  ]
     *
     * NOTE: If Finnish edit route is not yet implemented or differs, you can:
     *   - Temporarily skip the FI dataset in your consuming test
     *   - Or override this provider in a specific test class
     *
     * @return iterable<string,array{string,string,string}>
     */
    public static function provideProfileEditLocales(): iterable
    {
        yield 'en' => [
            'en',
            self::EN_EDIT_PATH,
            '/profile',      // expected redirect fragment (English)
        ];

        yield 'fi' => [
            'fi',
            self::FI_EDIT_PATH,
            self::FI_PROFILE_PATH, // expected redirect fragment (Finnish)
        ];
    }

    /**
     * Helper: build an edit path dynamically (if you prefer runtime construction).
     *
     * Falls back to defined constants for now—abstracted to allow future logic:
     *   e.g. adding site slug segments or year prefixes.
     */
    protected function buildProfileEditPath(string $locale): string
    {
        return match ($locale) {
            'en' => self::EN_EDIT_PATH,
            'fi' => self::FI_EDIT_PATH,
            default => sprintf('/%s/profile/edit', $locale),
        };
    }

    /**
     * Return the expected profile landing path fragment after successful form submission.
     * Useful to assert redirect targets in a locale-independent manner.
     */
    protected function expectedProfileFragment(string $locale): string
    {
        return match ($locale) {
            'en' => '/profile',
            'fi' => self::FI_PROFILE_PATH,
            default => '/profile',
        };
    }
}
