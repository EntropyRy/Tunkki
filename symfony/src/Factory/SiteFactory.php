<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Sonata\SonataPageSite;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * SiteFactory.
 *
 * Lightweight Foundry factory for creating a minimal SonataPageSite (Sonata PageBundle Site)
 * instance used by the immutable CMS baseline Story (CmsBaselineStory).
 *
 * Rationale (mirrors other domain factories):
 *  - Lives under src/ so it can be reused by stories, seeding scripts or future console
 *    commands without duplicating construction logic.
 *  - Keeps creation semantics colocated with the entity for contributor discoverability.
 *  - Defaults kept intentionally narrow to avoid accidental coupling; tests can
 *    override any attribute ad‑hoc.
 *
 * Notes:
 *  - The underlying Sonata BaseSite typically exposes setters like setName(), setEnabled(),
 *    setHost(), setLocale(), setRelativePath(), setIsDefault(). Only a subset is provided
 *    by default; override as needed when calling:
 *
 *        SiteFactory::new(['name' => 'My Site', 'host' => 'example.test'])->create();
 *
 *  - If any of the guessed attribute names differ in your concrete inherited entity
 *    (e.g. uses setIsDefault instead of setIsDefaultSite), adjust the keys below to match
 *    actual methods/properties. The factory relies on Foundry's mapping of array keys
 *    to public properties or setters (setXxx).
 *
 *  - This factory purposefully avoids persisting relations or snapshots; snapshot
 *    generation (if needed) should occur once in the CMS baseline story or a
 *    dedicated command to keep per‑test overhead low.
 *
 * @extends PersistentObjectFactory<SonataPageSite>
 */
final class SiteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SonataPageSite::class;
    }

    /**
     * Provide minimal deterministic defaults.
     *
     * Keep defaults stable; randomization here can obscure test intent and make
     * debugging route/host logic harder. Override explicitly in tests instead.
     */
    protected function defaults(): array
    {
        $now = new \DateTime(); // if timestamps exist & are auto-managed, this is harmless

        return [
            // Common BaseSite fields (adjust if actual setters differ):
            'name' => 'Default Site',
            'enabled' => true,
            'host' => 'localhost',
            'locale' => 'fi',
            // Provide a stable relative path (empty or /) depending on project convention.
            'relativePath' => '',

            // Mark as default; adjust key if BaseSite uses a different internal flag name.
            'isDefault' => true,
            // Site active window: ensure this site is considered active by Sonata's selector
            'enabledFrom' => $now->modify('-1 day'),
            'enabledTo' => null,
            // Additional commonly present optional fields can be uncommented/added as needed:
            // 'updatedAt' => $now,
            // 'createdAt' => $now,
        ];
    }

    /**
     * Post-instantiation hooks (none currently).
     *
     * Use ->afterInstantiate(fn(SonataPageSite $site) => ...) if you need to enforce
     * invariants after Foundry applies attributes.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}
