<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\PageService\FrontPage;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\StoryManager;

/**
 * PageFactory.
 *
 * Lightweight factory for producing minimal Sonata PageBundle Page entities (SonataPagePage)
 * used by the immutable CMS baseline story (CmsBaselineStory) and ad‑hoc test scenarios.
 *
 * Principles:
 *  - Deterministic defaults (no unnecessary randomness) to keep test diffs stable.
 *  - No automatic hierarchy building: tests explicitly opt-in to parent/child relations.
 *  - Leaves snapshot generation OUT of the factory (done once in a story/command if needed).
 *  - Supplies simple semantic states for common roles (homepage(), layout()).
 *
 * Relations & References:
 *  - The CmsBaselineStory can pass an existing Site reference when creating pages.
 *  - A convenience withSite() helper is provided. If null is passed and a default site
 *    reference exists (cms:site:default), the factory will try to attach it (optional).
 *
 * Usage Examples:
 *    $home = PageFactory::new()->homepage()->create();                 // Proxy<SonataPagePage>
 *    $layout = PageFactory::new(['name' => 'Layout'])->layout()->create();
 *    $page = PageFactory::new()->withSite($site)->create();
 *
 * Foundry Note:
 *  - You generally do NOT need to call ->object() unless you specifically require
 *    the underlying entity instance for identity-sensitive operations. The Proxy
 *    exposes entity methods transparently.
 *
 * @extends PersistentObjectFactory<SonataPagePage>
 */
final class PageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SonataPagePage::class;
    }

    /**
     * Minimal default attributes.
     *
     * Sonata BasePage typical fields include:
     *  - name, slug, routeName, enabled, position, type, templateCode,
     *    decorate, requestMethod.
     * (publicationDateStart/End & timestamps are managed internally by Sonata and
     *  omitted here to avoid property accessor issues in tests.)
     *
     * We set only a small stable subset; tests override explicitly for clarity.
     */
    protected function defaults(): array
    {
        new \DateTimeImmutable();

        return [
            'name' => 'Page',
            'slug' => 'page',                 // Adjusted in states or overrides
            'routeName' => 'page_generic',    // Non-home template route placeholder
            'enabled' => true,
            'position' => 1,
            'decorate' => true,
            'type' => 'route',                // Common simple default in Sonata setups
            'templateCode' => 'default',      // Adjust if your project uses custom templates
            'requestMethod' => null,
            // publicationDateStart/End & timestamps omitted (Sonata BasePage manages these;
            // removing them prevents accessor errors when underlying implementation
            // lacks public setters or different naming).
        ];
    }

    /**
     * Post-instantiation adjustments.
     *
     * We normalize slug/name coherence: if slug missing but name present, derive a simple slug.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (SonataPagePage $page): void {
            // Derive a slug if empty and name exists (defensive).
            if (method_exists($page, 'getSlug')
                && method_exists($page, 'setSlug')
                && method_exists($page, 'getName')
                && method_exists($page, 'setName')
            ) {
                $slug = $page->getSlug();
                $name = $page->getName();
                if ((null === $slug || '' === $slug) && $name) {
                    $normalized = strtolower(preg_replace('#[^a-z0-9]+#', '-', $name) ?? '');
                    $page->setSlug(trim($normalized ?: 'page', '-'));
                }
            }
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Mark this page as the homepage (root).
     * Adjust attribute keys if BasePage uses different semantics (e.g., isHome).
     */
    public function homepage(): static
    {
        // Root pages must have url='/' and slug='' for all locales; site.relativePath provides '/en' when applicable.
        return $this->with([
            'name' => 'Homepage',
            'title' => 'Homepage',
            'slug' => '',
            'url' => '/',
            'routeName' => 'page_slug',
            'position' => 1,
            'templateCode' => 'frontpage',
            'type' => FrontPage::class,
            'enabled' => true,
            'decorate' => true,
            'requestMethod' => 'GET|POST|HEAD|DELETE|PUT',
        ]);
    }

    /**
     * A generic layout or parent container page (optional structure).
     */
    public function layout(string $name = 'Layout'): static
    {
        return $this->with([
            'name' => $name,
            'slug' => 'layout',
            'routeName' => 'layout',
            'position' => 2,
            'templateCode' => 'layout',
        ]);
    }

    /**
     * Mark page disabled (not published to front-end).
     */
    public function disabled(): static
    {
        return $this->with([
            'enabled' => false,
        ]);
    }

    /**
     * Attach (or create) a site relation.
     *
     * @param SonataPageSite|Proxy<SonataPageSite>|null $site
     */
    public function withSite(SonataPageSite|Proxy|null $site = null): static
    {
        // If explicit site provided, just set it.
        if (null !== $site) {
            return $this->with(['site' => $site]);
        }

        // Attempt to reuse default reference if accessible. We cannot rely on Foundry's
        // global references here directly without a guard; so we reflect if Story loaded.
        if (class_exists(StoryManager::class)) {
            try {
                /** @phpstan-ignore-next-line */
                $manager = StoryManager::instance();
                if ($manager->has('cms:site:default')) {
                    /** @var SonataPageSite $defaultSite */
                    $defaultSite = $manager->get('cms:site:default');

                    return $this->with(['site' => $defaultSite]);
                }
            } catch (\Throwable) {
                // Non-fatal: Story not loaded yet or Foundry manager unavailable.
            }
        }

        return $this;
    }

    /**
     * Assign a parent page (expects another Page entity/proxy).
     *
     * @param SonataPagePage|Proxy<SonataPagePage> $parent
     */
    public function withParent(SonataPagePage|Proxy $parent): static
    {
        return $this->with(['parent' => $parent]);
    }

    /**
     * Convenience for giving the page a deterministic slug (overrides derived/default).
     */
    public function withSlug(string $slug): static
    {
        return $this->with(['slug' => $slug]);
    }

    /**
     * Explicit template override.
     */
    public function withTemplate(string $templateCode): static
    {
        return $this->with(['templateCode' => $templateCode]);
    }
}
