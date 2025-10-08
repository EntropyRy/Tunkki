<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;

/**
 * FormErrorAssertionTrait.
 *
 * Provides reusable, framework-agnostic (but Symfony‑form friendly) helpers for asserting
 * presence / absence of form validation errors in functional tests.
 *
 * Motivation:
 *   - Avoid brittle raw substring assertions against full HTML.
 *   - Centralize knowledge of multiple possible error markup patterns used
 *     across templates (Bootstrap, legacy, custom, Sonata embedded forms, etc.).
 *   - Produce intention‑revealing test code with standardized failure messages.
 *
 * Supported default selectors (scanned in order):
 *   GLOBAL (non field-specific):
 *     - .form-errors li
 *     - .form-error-message
 *     - .alert.alert-danger li
 *     - .alert.alert-danger
 *     - .invalid-feedback (when not scoped to an input)
 *   FIELD-SCOPED:
 *     - [data-field-error-for="%name%"]
 *     - .form-error-%name%
 *     - .form-group:has([name="%name%"]) .invalid-feedback
 *     - [name="%name%"] ~ .invalid-feedback
 *     - [name="%name%"] ~ .form-error-message
 *     - .errorlist li:contains("%name%")
 *
 * Override / extend:
 *   A test class can override the protected arrays:
 *     $formGlobalErrorSelectors
 *     $formFieldErrorSelectorTemplates
 *
 * Example usage in a WebTestCase descendant:
 *
 *   use App\Tests\Support\FormErrorAssertionTrait;
 *
 *   $crawler = $client->submit($form);
 *   $this->assertFormFieldHasError($crawler, 'email', 'valid email');
 *   $this->assertFormHasGlobalError($crawler, 'There were problems');
 *   $this->assertFormErrorCount($crawler, 2);
 *
 * All assertion methods throw PHPUnit assertion failures on mismatch.
 */
trait FormErrorAssertionTrait
{
    /**
     * CSS/XPath-like selectors considered to represent global (non field-specific) errors.
     * Order matters: first matches are used for extraction; later are fallbacks.
     *
     * NOTE: The DomCrawler does not support :contains() natively in CSS; for textual
     * filtering we collect then filter in PHP.
     *
     * @var list<string>
     */
    protected array $formGlobalErrorSelectors = [
        '.form-errors li',
        '.form-error-message',
        '.alert.alert-danger li',
        '.alert.alert-danger',
        '.invalid-feedback', // Only treated as global if not field-scoped (see extractor)
        '.help-block ul li',
        '.errorlist li',
    ];

    /**
     * Templates to locate field-specific error containers. The "%s" placeholder will
     * be replaced with the raw field name (no escaping done—use simple names).
     *
     * @var list<string>
     */
    protected array $formFieldErrorSelectorTemplates = [
        '[data-field-error-for="%s"]',
        '.form-error-%s',
        'div.form-group:has([name="%s"]) .invalid-feedback',
        '[name="%s"] ~ .invalid-feedback',
        '[name="%s"] ~ .form-error-message',
        '.errorlist li', // Will be content-filtered by field name
    ];

    /**
     * Assert that at least one global form error exists and optionally that one
     * contains a given substring (case-insensitive).
     */
    protected function assertFormHasGlobalError(Crawler $crawler, ?string $messageContains = null): void
    {
        $errors = $this->extractFormGlobalErrors($crawler);

        Assert::assertNotEmpty(
            $errors,
            'Expected at least one global form error, none found.'
        );

        if (null !== $messageContains) {
            $needle = mb_strtolower($messageContains);
            $matched = array_filter(
                $errors,
                static fn (string $e): bool => str_contains(mb_strtolower($e), $needle)
            );
            Assert::assertNotEmpty(
                $matched,
                sprintf(
                    'No global form error contained expected fragment "%s". Errors: [%s]',
                    $messageContains,
                    implode(' | ', $errors)
                )
            );
        }
    }

    /**
     * Assert that there are no global form errors.
     */
    protected function assertFormHasNoGlobalErrors(Crawler $crawler): void
    {
        $errors = $this->extractFormGlobalErrors($crawler);
        Assert::assertEmpty(
            $errors,
            sprintf(
                'Expected no global form errors, found: [%s]',
                implode(' | ', $errors)
            )
        );
    }

    /**
     * Assert a specific field has at least one validation error.
     * Optionally assert that at least one error message contains a substring.
     *
     * @param string      $fieldName        Field name as submitted in form (e.g. "email" or "user[profile][email]")
     * @param string|null $expectedFragment Optional case-insensitive substring expected in one of the error messages
     */
    protected function assertFormFieldHasError(
        Crawler $crawler,
        string $fieldName,
        ?string $expectedFragment = null,
    ): void {
        $messages = $this->extractFormFieldErrors($crawler, $fieldName);

        Assert::assertNotEmpty(
            $messages,
            sprintf('Expected at least one error for field "%s" but none found.', $fieldName)
        );

        if (null !== $expectedFragment) {
            $needle = mb_strtolower($expectedFragment);
            $matched = array_filter(
                $messages,
                static fn (string $m): bool => str_contains(mb_strtolower($m), $needle)
            );
            Assert::assertNotEmpty(
                $matched,
                sprintf(
                    'Field "%s" error messages did not contain fragment "%s". Messages: [%s]',
                    $fieldName,
                    $expectedFragment,
                    implode(' | ', $messages)
                )
            );
        }
    }

    /**
     * Assert a field has no validation errors.
     */
    protected function assertFormFieldHasNoError(Crawler $crawler, string $fieldName): void
    {
        $messages = $this->extractFormFieldErrors($crawler, $fieldName);
        Assert::assertEmpty(
            $messages,
            sprintf(
                'Expected no errors for field "%s" but found: [%s]',
                $fieldName,
                implode(' | ', $messages)
            )
        );
    }

    /**
     * Assert the total number of (global + field) error lines equals expectation.
     * This can be useful after submitting an intentionally invalid form with a known
     * set of triggered constraints.
     *
     * NOTE: This counts each distinct error text instance after normalization.
     */
    protected function assertFormErrorCount(Crawler $crawler, int $expectedCount): void
    {
        $all = $this->extractAllFormErrors($crawler);
        Assert::assertCount(
            $expectedCount,
            $all,
            sprintf(
                'Expected %d total form errors, found %d: [%s]',
                $expectedCount,
                count($all),
                implode(' | ', $all)
            )
        );
    }

    /**
     * Extract ALL visible form error messages (global + field) for diagnostics.
     *
     * @return list<string>
     */
    protected function extractAllFormErrors(Crawler $crawler): array
    {
        $global = $this->extractFormGlobalErrors($crawler);
        $field = $this->extractAllFieldErrorsByHeuristics($crawler);

        // Merge and deduplicate while preserving order
        $merged = [];
        foreach (array_merge($global, $field) as $msg) {
            if ('' === $msg) {
                continue;
            }
            if (!in_array($msg, $merged, true)) {
                $merged[] = $msg;
            }
        }

        return $merged;
    }

    /**
     * Extract global (non field-scoped) errors by scanning known selectors.
     *
     * @return list<string>
     */
    protected function extractFormGlobalErrors(Crawler $crawler): array
    {
        $collected = [];

        foreach ($this->formGlobalErrorSelectors as $selector) {
            $nodes = $crawler->filter($selector);
            if (0 === $nodes->count()) {
                continue;
            }

            $nodes->each(static function (Crawler $n) use (&$collected): void {
                $text = trim(preg_replace('/\s+/', ' ', $n->text('')));
                if ('' !== $text) {
                    $collected[] = $text;
                }
            });
        }

        // Heuristic: if everything matched only raw container with no <li>, we still include it.
        return $this->normalizeUnique($collected);
    }

    /**
     * Extract error messages for a specific field name by applying selector templates
     * and then filtering for textual mention ONLY when a selector is generic (like .errorlist li).
     *
     * @return list<string>
     */
    protected function extractFormFieldErrors(Crawler $crawler, string $fieldName): array
    {
        $fieldNameLower = mb_strtolower($fieldName);
        $collected = [];

        foreach ($this->formFieldErrorSelectorTemplates as $template) {
            $selector = sprintf($template, $fieldName);

            $nodes = $crawler->filter($selector);
            if (0 === $nodes->count()) {
                continue;
            }

            $nodes->each(static function (Crawler $n) use (&$collected): void {
                $text = trim(preg_replace('/\s+/', ' ', $n->text('')));
                if ('' !== $text) {
                    $collected[] = $text;
                }
            });
        }

        // Fallback heuristic: sometimes errors do not incorporate field name; if no messages found,
        // attempt to locate nearest '.invalid-feedback' after the input.
        if (empty($collected)) {
            $inputNodes = $crawler->filter(sprintf('[name="%s"]', $fieldName));
            if ($inputNodes->count() > 0) {
                $inputNode = $inputNodes->first();
                // Attempt sibling invalid-feedback (approximation; DomCrawler lacks direct sibling CSS advanced features reliably).
                $maybe = $crawler->filter('.invalid-feedback');
                if ($maybe->count() > 0 && $inputNode->count() > 0) {
                    $maybe->each(static function (Crawler $n) use (&$collected): void {
                        $text = trim(preg_replace('/\s+/', ' ', $n->text('')));
                        if ('' !== $text) {
                            $collected[] = $text;
                        }
                    });
                }
            }
        }

        // Filter duplicates and return
        return $this->normalizeUnique($collected);
    }

    /**
     * Attempt to collect all field-level errors without specifying a field (used for total count).
     *
     * @return list<string>
     */
    protected function extractAllFieldErrorsByHeuristics(Crawler $crawler): array
    {
        $selectors = [
            '.invalid-feedback',
            '.form-error-message',
            '.form-errors li',
            '.errorlist li',
            '.help-block ul li',
        ];

        $collected = [];
        foreach ($selectors as $sel) {
            $nodes = $crawler->filter($sel);
            if (0 === $nodes->count()) {
                continue;
            }
            $nodes->each(static function (Crawler $n) use (&$collected): void {
                $text = trim(preg_replace('/\s+/', ' ', $n->text('')));
                if ('' !== $text) {
                    $collected[] = $text;
                }
            });
        }

        return $this->normalizeUnique($collected);
    }

    /**
     * Normalize & deduplicate a list of error strings.
     *
     * @param list<string> $messages
     *
     * @return list<string>
     */
    private function normalizeUnique(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $norm = trim($m);
            if ('' === $norm) {
                continue;
            }
            if (!in_array($norm, $out, true)) {
                $out[] = $norm;
            }
        }

        return $out;
    }
}
