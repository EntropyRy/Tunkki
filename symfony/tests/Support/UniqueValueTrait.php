<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * UniqueValueTrait.
 *
 * Purpose:
 *  - Provide collision‑resistant helpers for generating unique slugs, emails and generic tokens
 *    in a now fixture‑free functional test environment.
 *  - Centralizes randomness strategy so future changes (e.g. deterministic seeds, shorter IDs)
 *    happen in one place.
 *
 * Rationale (Decision 2025-10-03 – Fixture-Free Suite):
 *  Functional tests no longer rely on preloaded Doctrine fixtures. All entities must be created
 *  inside each test via factories. Static slugs/emails previously caused unique constraint
 *  violations when tests were re-run or when fixtures were present. These helpers remove the
 *  need for ad-hoc uniqid()/random_bytes() scattering throughout tests.
 *
 * Design Notes:
 *  - Slugs: Lowercase, [a-z0-9-], appended short random segment to provided base.
 *  - Emails: Local part uses a caller-provided prefix plus a short random hex segment.
 *  - Tokens: Hex string of defined entropy (default 16 raw bytes => 32 hex chars).
 *  - All randomness uses random_bytes() (cryptographically strong, but negligible perf impact
 *    at current test scales).
 *  - Length compromise: short enough for readable test diffs, long enough to avoid collisions.
 *
 * Usage (Examples):
 *    $slug  = $this->uniqueSlug('event');
 *    $email = $this->uniqueEmail('admin');
 *    $token = $this->uniqueToken(8); // 16 hex chars
 *
 * Future Extension:
 *  - Add sequence-backed deterministic mode if parallel test splitting ever requires reproducible
 *    identifiers (gate behind an env var e.g. TEST_DETERMINISTIC_IDS=1).
 */
trait UniqueValueTrait
{
    /**
     * Generate a unique slug derived from a base segment.
     *
     * @param string $base        Base slug prefix (will be normalized)
     * @param int    $randomBytes Number of random bytes (>=2 recommended)
     */
    protected function uniqueSlug(string $base = 'test', int $randomBytes = 4): string
    {
        $base = strtolower(trim($base));
        $base = preg_replace('#[^a-z0-9-]+#', '-', $base) ?: 'test';
        $base = trim(preg_replace('#-+#', '-', $base) ?? '', '-');

        $rand = bin2hex(random_bytes(max(2, $randomBytes)));

        return \sprintf('%s-%s', $base, $rand);
    }

    /**
     * Generate a unique email address.
     *
     * Pattern: <localPrefix>+<hex>@example.test
     *
     * @param string $localPrefix Human-friendly local part base
     * @param int    $randomBytes Entropy size; 3–5 is usually sufficient
     */
    protected function uniqueEmail(string $localPrefix = 'user', int $randomBytes = 4): string
    {
        $localPrefix = strtolower(trim($localPrefix));
        $localPrefix = preg_replace('#[^a-z0-9._-]+#', '-', $localPrefix) ?: 'user';
        $localPrefix = trim(preg_replace('#-+#', '-', $localPrefix) ?? '', '-');

        $rand = bin2hex(random_bytes(max(2, $randomBytes)));

        return \sprintf('%s+%s@example.test', $localPrefix, $rand);
    }

    /**
     * Generate a hex token (utility for CSRF-like placeholders, reference IDs, etc.).
     *
     * @param int $randomBytes Raw bytes (each becomes 2 hex chars)
     */
    protected function uniqueToken(int $randomBytes = 16): string
    {
        return bin2hex(random_bytes(max(4, $randomBytes)));
    }

    /**
     * Convenience: generate a slug guaranteed not to exceed a maximum length.
     *
     * If total length (base + hyphen + random) exceeds $maxLength, the base is truncated.
     *
     * @param int $maxLength   Absolute max length of returned slug
     * @param int $randomBytes Entropy for suffix
     */
    protected function boundedUniqueSlug(string $base, int $maxLength = 64, int $randomBytes = 4): string
    {
        $suffix = bin2hex(random_bytes(max(2, $randomBytes)));
        $normalizedBase = preg_replace('#[^a-z0-9-]+#', '-', strtolower(trim($base))) ?: 'slug';
        $normalizedBase = trim(preg_replace('#-+#', '-', $normalizedBase) ?? '', '-');

        $reserved = \strlen($suffix) + 1; // hyphen + suffix
        if ($reserved >= $maxLength) {
            // Edge case: fallback to suffix only (trim if still over)
            return substr($suffix, 0, $maxLength);
        }

        $truncated = substr($normalizedBase, 0, $maxLength - $reserved);

        return \sprintf('%s-%s', rtrim($truncated, '-'), $suffix);
    }
}
