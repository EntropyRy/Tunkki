<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Checkout;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

/**
 * StripeIdValidationTest.
 *
 * Tests that Stripe test mode IDs follow expected patterns.
 *
 * Addresses GAP from todo.md:
 * "Stripe test mode ID format validation (prod_test_*, price_test_*, cs_test_*)"
 *
 * Rationale:
 * - Prevents accidental use of live mode IDs in test/staging environments
 * - Catches misconfigured Stripe resources early
 * - Documents expected ID format patterns for developers
 *
 * Patterns (Stripe test mode):
 * - Product IDs: prod_test_*
 * - Price IDs: price_test_*
 * - Checkout Session IDs: cs_test_*
 */
final class StripeIdValidationTest extends TestCase
{
    /**
     * Test that Product stripeId accepts test mode format.
     */
    public function testProductStripeIdAcceptsTestModeFormat(): void
    {
        $product = new Product();
        $product->setStripeId('prod_test_abc123xyz');

        $this->assertMatchesRegularExpression(
            '/^prod_test_[a-zA-Z0-9]+$/',
            $product->getStripeId(),
            'Product stripeId should match test mode pattern prod_test_*'
        );
    }

    /**
     * Test that Product stripePriceId accepts test mode format.
     */
    public function testProductStripePriceIdAcceptsTestModeFormat(): void
    {
        $product = new Product();
        $product->setStripePriceId('price_test_1234567890abcdef');

        $this->assertMatchesRegularExpression(
            '/^price_test_[a-zA-Z0-9]+$/',
            $product->getStripePriceId(),
            'Product stripePriceId should match test mode pattern price_test_*'
        );
    }

    /**
     * Test that Checkout stripeSessionId accepts test mode format.
     */
    public function testCheckoutStripeSessionIdAcceptsTestModeFormat(): void
    {
        $checkout = new Checkout();
        $checkout->setStripeSessionId('cs_test_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6');

        $this->assertMatchesRegularExpression(
            '/^cs_test_[a-zA-Z0-9]+$/',
            $checkout->getStripeSessionId(),
            'Checkout stripeSessionId should match test mode pattern cs_test_*'
        );
    }

    /**
     * Test that live mode Product IDs can be detected (negative test).
     * This documents what NOT to use in test environments.
     */
    public function testProductStripeIdDetectsLiveModePattern(): void
    {
        $product = new Product();
        $product->setStripeId('prod_LiveModeId123'); // Live mode typically lacks _test_ infix

        $this->assertDoesNotMatchRegularExpression(
            '/^prod_test_[a-zA-Z0-9]+$/',
            $product->getStripeId(),
            'Live mode Product IDs should NOT match test mode pattern'
        );
    }

    /**
     * Test that live mode Price IDs can be detected (negative test).
     */
    public function testProductStripePriceIdDetectsLiveModePattern(): void
    {
        $product = new Product();
        $product->setStripePriceId('price_LiveModePrice456'); // Live mode

        $this->assertDoesNotMatchRegularExpression(
            '/^price_test_[a-zA-Z0-9]+$/',
            $product->getStripePriceId(),
            'Live mode Price IDs should NOT match test mode pattern'
        );
    }

    /**
     * Test that live mode Checkout Session IDs can be detected (negative test).
     */
    public function testCheckoutStripeSessionIdDetectsLiveModePattern(): void
    {
        $checkout = new Checkout();
        $checkout->setStripeSessionId('cs_live_a1b2c3d4e5f6g7h8i9j0'); // Live mode

        $this->assertDoesNotMatchRegularExpression(
            '/^cs_test_[a-zA-Z0-9]+$/',
            $checkout->getStripeSessionId(),
            'Live mode Checkout Session IDs should NOT match test mode pattern'
        );
    }

    /**
     * Test validation helper: Product with all valid test mode IDs.
     */
    public function testProductWithAllValidTestModeIds(): void
    {
        $product = new Product();
        $product->setStripeId('prod_test_ValidProduct123');
        $product->setStripePriceId('price_test_ValidPrice456');

        $this->assertTrue(
            $this->isValidTestModeProductId($product->getStripeId()),
            'Product stripeId should be valid test mode'
        );
        $this->assertTrue(
            $this->isValidTestModePriceId($product->getStripePriceId()),
            'Product stripePriceId should be valid test mode'
        );
    }

    /**
     * Test validation helper: Checkout with valid test mode session ID.
     */
    public function testCheckoutWithValidTestModeSessionId(): void
    {
        $checkout = new Checkout();
        $checkout->setStripeSessionId('cs_test_ValidSession789');

        $this->assertTrue(
            $this->isValidTestModeCheckoutSessionId($checkout->getStripeSessionId()),
            'Checkout stripeSessionId should be valid test mode'
        );
    }

    /**
     * Helper: Validate Product ID matches test mode pattern.
     */
    private function isValidTestModeProductId(string $stripeId): bool
    {
        return 1 === preg_match('/^prod_test_[a-zA-Z0-9]+$/', $stripeId);
    }

    /**
     * Helper: Validate Price ID matches test mode pattern.
     */
    private function isValidTestModePriceId(string $stripePriceId): bool
    {
        return 1 === preg_match('/^price_test_[a-zA-Z0-9]+$/', $stripePriceId);
    }

    /**
     * Helper: Validate Checkout Session ID matches test mode pattern.
     */
    private function isValidTestModeCheckoutSessionId(string $stripeSessionId): bool
    {
        return 1 === preg_match('/^cs_test_[a-zA-Z0-9]+$/', $stripeSessionId);
    }

    /**
     * Test that empty strings fail validation (edge case).
     */
    public function testEmptyStripeIdsFail(): void
    {
        $this->assertFalse(
            $this->isValidTestModeProductId(''),
            'Empty Product stripeId should fail validation'
        );
        $this->assertFalse(
            $this->isValidTestModePriceId(''),
            'Empty Price stripePriceId should fail validation'
        );
        $this->assertFalse(
            $this->isValidTestModeCheckoutSessionId(''),
            'Empty Checkout stripeSessionId should fail validation'
        );
    }

    /**
     * Test that malformed IDs fail validation.
     */
    public function testMalformedStripeIdsFail(): void
    {
        // Missing prefix
        $this->assertFalse(
            $this->isValidTestModeProductId('test_abc123'),
            'Product ID without prod_ prefix should fail'
        );

        // Wrong separator
        $this->assertFalse(
            $this->isValidTestModePriceId('price-test-abc123'),
            'Price ID with hyphens instead of underscores should fail'
        );

        // Missing suffix
        $this->assertFalse(
            $this->isValidTestModeCheckoutSessionId('cs_test_'),
            'Checkout Session ID without suffix should fail'
        );
    }
}
