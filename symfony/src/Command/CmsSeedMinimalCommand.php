<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\PageService\FrontPage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[
    AsCommand(
        name: 'cms:seed:minimal',
        description: 'Seed the base minimum Sonata CMS sites and pages (FI "", EN "/en"; root + alias pages). Idempotent.',
    ),
]
final class CmsSeedMinimalCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $siteR = $this->em->getRepository(SonataPageSite::class);
        $pageR = $this->em->getRepository(SonataPagePage::class);

        $created = ['site' => 0, 'page' => 0];
        $updated = ['site' => 0, 'page' => 0];

        // Ensure Sites (localhost host-by-locale)
        [$fiSite, $fiChanged, $fiCreated] = $this->getOrCreateSite(
            locale: 'fi',
            relativePath: '',
            isDefault: true,
            host: 'localhost',
            now: $now,
        );
        $created['site'] += $fiCreated ? 1 : 0;
        $updated['site'] += $fiChanged ? 1 : 0;

        [$enSite, $enChanged, $enCreated] = $this->getOrCreateSite(
            locale: 'en',
            relativePath: '/en',
            isDefault: false,
            host: 'localhost',
            now: $now,
        );
        $created['site'] += $enCreated ? 1 : 0;
        $updated['site'] += $enChanged ? 1 : 0;

        // Ensure only FI is default
        $flip = false;
        if (!$fiSite->getIsDefault()) {
            $fiSite->setIsDefault(true);
            $flip = true;
        }
        if ($enSite->getIsDefault()) {
            $enSite->setIsDefault(false);
            $flip = true;
        }
        if ($flip) {
            $this->em->persist($fiSite);
            $this->em->persist($enSite);
            $this->em->flush();
            $updated['site'] += 2;
        }

        // Ensure ROOT page per site
        [$fiRoot, $fiRootCreated, $fiRootChanged] = $this->getOrCreateRootPage(
            $fiSite,
        );
        $created['page'] += $fiRootCreated ? 1 : 0;
        $updated['page'] += $fiRootChanged ? 1 : 0;

        [$enRoot, $enRootCreated, $enRootChanged] = $this->getOrCreateRootPage(
            $enSite,
        );
        $created['page'] += $enRootCreated ? 1 : 0;
        $updated['page'] += $enRootChanged ? 1 : 0;

        // Ensure alias pages used by templates (Events, Join Us) for each site
        [$fiEvents, $c1, $u1] = $this->getOrCreateAliasPage(
            site: $fiSite,
            parent: $fiRoot,
            alias: '_page_alias_events_fi',
            slug: 'tapahtumat',
            url: '/tapahtumat',
            name: 'Tapahtumat',
            type: 'entropy.page.eventspage',
            template: 'events',
        );
        $created['page'] += $c1 ? 1 : 0;
        $updated['page'] += $u1 ? 1 : 0;

        [$enEvents, $c2, $u2] = $this->getOrCreateAliasPage(
            site: $enSite,
            parent: $enRoot,
            alias: '_page_alias_events_en',
            slug: 'events',
            url: '/events',
            name: 'Events',
            type: 'entropy.page.eventspage',
            template: 'events',
        );
        $created['page'] += $c2 ? 1 : 0;
        $updated['page'] += $u2 ? 1 : 0;

        [$fiJoin, $c3, $u3] = $this->getOrCreateAliasPage(
            site: $fiSite,
            parent: $fiRoot,
            alias: '_page_alias_join_us_fi',
            slug: 'liity',
            url: '/liity',
            name: 'Liity',
            type: 'sonata.page.service.default',
            template: 'onecolumn',
            title: 'Liity Jäseneksi',
        );
        $created['page'] += $c3 ? 1 : 0;
        $updated['page'] += $u3 ? 1 : 0;

        [$enJoin, $c4, $u4] = $this->getOrCreateAliasPage(
            site: $enSite,
            parent: $enRoot,
            alias: '_page_alias_join_us_en',
            slug: 'join-us',
            url: '/join-us',
            name: 'Join Us',
            type: 'sonata.page.service.default',
            template: 'onecolumn',
        );
        $created['page'] += $c4 ? 1 : 0;
        $updated['page'] += $u4 ? 1 : 0;

        // Ensure Announcements listing page (per locale)
        [$fiAnn, $c5, $u5] = $this->getOrCreateAliasPage(
            site: $fiSite,
            parent: $fiRoot,
            alias: '_page_alias_announcements_fi',
            slug: 'tiedotukset',
            url: '/tiedotukset',
            name: 'Tiedotukset',
            type: 'entropy.page.announcementspage',
            template: 'annnouncements',
        );
        $created['page'] += $c5 ? 1 : 0;
        $updated['page'] += $u5 ? 1 : 0;

        [$enAnn, $c6, $u6] = $this->getOrCreateAliasPage(
            site: $enSite,
            parent: $enRoot,
            alias: '_page_alias_announcements_en',
            slug: 'announcements',
            url: '/announcements',
            name: 'Announcements',
            type: 'entropy.page.announcementspage',
            template: 'annnouncements',
        );
        $created['page'] += $c6 ? 1 : 0;
        $updated['page'] += $u6 ? 1 : 0;

        // Ensure Stream page (per locale)
        [$fiStream, $c7, $u7] = $this->getOrCreateAliasPage(
            site: $fiSite,
            parent: $fiRoot,
            alias: '_page_alias_stream_fi',
            slug: 'stream',
            url: '/stream',
            name: 'Stream',
            type: 'entropy.page.stream',
            template: 'stream',
        );
        $created['page'] += $c7 ? 1 : 0;
        $updated['page'] += $u7 ? 1 : 0;

        [$enStream, $c8, $u8] = $this->getOrCreateAliasPage(
            site: $enSite,
            parent: $enRoot,
            alias: '_page_alias_stream_en',
            slug: 'stream',
            url: '/stream',
            name: 'Stream',
            type: 'entropy.page.stream',
            template: 'stream',
        );
        $created['page'] += $c8 ? 1 : 0;
        $updated['page'] += $u8 ? 1 : 0;

        $siteCount = $siteR->count([]);
        $pageCount = $pageR->count([]);

        $io->success(
            \sprintf(
                'CMS minimal seed completed. sites: +%d created, ~%d updated; pages: +%d created, ~%d updated. Totals => sites=%d, pages=%d',
                $created['site'],
                $updated['site'],
                $created['page'],
                $updated['page'],
                $siteCount,
                $pageCount,
            ),
        );

        $io->writeln(
            \sprintf(
                'Root FI: id=%s; Root EN: id=%s; Events Aliases: fi=%s en=%s; Join Aliases: fi=%s en=%s; Stream Aliases: fi=%s en=%s',
                $fiRoot->getId() ?: 'new',
                $enRoot->getId() ?: 'new',
                $fiEvents->getPageAlias(),
                $enEvents->getPageAlias(),
                $fiJoin->getPageAlias(),
                $enJoin->getPageAlias(),
                $fiStream->getPageAlias(),
                $enStream->getPageAlias(),
            ),
        );

        return Command::SUCCESS;
    }

    /**
     * Ensure a site exists for locale with expected properties.
     *
     * @return array{0: SonataPageSite, 1: bool, 2: bool} tuple(site, changed, created)
     */
    private function getOrCreateSite(
        string $locale,
        string $relativePath,
        bool $isDefault,
        string $host,
        \DateTimeImmutable $now,
    ): array {
        /** @var EntityRepository<SonataPageSite> $repo */
        $repo = $this->em->getRepository(SonataPageSite::class);

        /** @var ?SonataPageSite $site */
        $site = $repo->findOneBy(['host' => $host, 'locale' => $locale]);

        $created = false;
        $changed = false;

        if (!$site instanceof SonataPageSite) {
            $site = new SonataPageSite();
            $site->setName(strtoupper($locale));
            $site->setEnabled(true);
            $site->setHost($host);
            $site->setLocale($locale);
            $site->setIsDefault($isDefault);
            $site->setEnabledFrom($now->modify('-1 day'));
            $site->setEnabledTo(null);
            $site->setRelativePath($relativePath);
            $this->em->persist($site);
            $this->em->flush();
            $created = true;
        } else {
            // Normalize expected props
            $changed = $this->normalizeSite(
                $site,
                $locale,
                $relativePath,
                $isDefault,
                $host,
                $now,
            );
        }

        if ($changed) {
            $this->em->persist($site);
            $this->em->flush();
        }

        return [$site, $changed, $created];
    }

    private function normalizeSite(
        SonataPageSite $site,
        string $locale,
        string $relativePath,
        bool $isDefault,
        string $host,
        \DateTimeImmutable $now,
    ): bool {
        $changed = false;

        if ($site->getHost() !== $host) {
            $site->setHost($host);
            $changed = true;
        }

        if ($site->getLocale() !== $locale) {
            $site->setLocale($locale);
            $changed = true;
        }

        $current = $site->getRelativePath() ?? '';
        if ($current !== $relativePath) {
            $site->setRelativePath($relativePath);
            $changed = true;
        }

        // We let FI win later; here we simply ensure expected default for the target
        if ((bool) $site->getIsDefault() !== $isDefault) {
            $site->setIsDefault($isDefault);
            $changed = true;
        }

        if (!$site->getEnabled()) {
            $site->setEnabled(true);
            $changed = true;
        }

        // Normalize active window explicitly
        $site->setEnabledFrom($now->modify('-1 day'));

        $site->setEnabledTo(null);

        return true;
    }

    /**
     * Ensure the root page (/) exists for a site.
     *
     * @return array{0: SonataPagePage, 1: bool, 2: bool} tuple(page, created, changed)
     */
    private function getOrCreateRootPage(SonataPageSite $site): array
    {
        /** @var EntityRepository<SonataPagePage> $pageRepo */
        $pageRepo = $this->em->getRepository(SonataPagePage::class);

        /** @var ?SonataPagePage $root */
        $root = $pageRepo->findOneBy(['site' => $site, 'url' => '/']);

        $created = false;
        $changed = false;

        if (!$root instanceof SonataPagePage) {
            $root = new SonataPagePage();
            $root->setSite($site);
            $root->setName('en' === $site->getLocale() ? 'Home' : 'Etusivu');
            $root->setTitle($root->getName());
            $root->setSlug('');
            $root->setUrl('/');
            $root->setRouteName('page_slug');
            $root->setEnabled(true);
            $root->setDecorate(true);
            $root->setTemplateCode('frontpage');
            $root->setType(FrontPage::class);
            $root->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
            $this->em->persist($root);
            $this->em->flush();
            $created = true;
        } else {
            $changed = $this->normalizeRootPage($root, $site);
            if ($changed) {
                $this->em->persist($root);
                $this->em->flush();
            }
        }

        return [$root, $created, $changed];
    }

    private function normalizeRootPage(
        SonataPagePage $page,
        SonataPageSite $site,
    ): bool {
        $changed = false;

        $expectedName = 'en' === $site->getLocale() ? 'Home' : 'Etusivu';
        if ($page->getName() !== $expectedName) {
            $page->setName($expectedName);
            $page->setTitle($expectedName);
            $changed = true;
        }
        if ('/' !== $page->getUrl()) {
            $page->setUrl('/');
            $changed = true;
        }
        if ('' !== $page->getSlug()) {
            $page->setSlug('');
            $changed = true;
        }
        if ('page_slug' !== $page->getRouteName()) {
            $page->setRouteName('page_slug');
            $changed = true;
        }
        if ('frontpage' !== $page->getTemplateCode()) {
            $page->setTemplateCode('frontpage');
            $changed = true;
        }
        if (FrontPage::class !== $page->getType()) {
            $page->setType(FrontPage::class);
            $changed = true;
        }
        if (!$page->getEnabled()) {
            $page->setEnabled(true);
            $changed = true;
        }
        if (!$page->getDecorate()) {
            $page->setDecorate(true);
            $changed = true;
        }
        if ('GET|POST|HEAD|DELETE|PUT' !== $page->getRequestMethod()) {
            $page->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
            $changed = true;
        }

        return $changed;
    }

    /**
     * Ensure an alias page exists (events/join us). Idempotent.
     *
     * @return array{0: SonataPagePage, 1: bool, 2: bool} tuple(page, created, changed)
     */
    private function getOrCreateAliasPage(
        SonataPageSite $site,
        SonataPagePage $parent,
        string $alias,
        string $slug,
        string $url,
        string $name,
        string $type,
        string $template,
        ?string $title = null,
    ): array {
        /** @var EntityRepository<SonataPagePage> $pageRepo */
        $pageRepo = $this->em->getRepository(SonataPagePage::class);

        /** @var ?SonataPagePage $page */
        $page =
            $pageRepo->findOneBy(['site' => $site, 'pageAlias' => $alias]) ??
            ($pageRepo->findOneBy(['site' => $site, 'slug' => $slug]) ??
                $pageRepo->findOneBy(['site' => $site, 'url' => $url]));

        $created = false;
        $changed = false;

        if (!$page instanceof SonataPagePage) {
            $page = new SonataPagePage();
            $page->setSite($site);
            $page->setParent($parent);
            $page->setPosition(1);
            $created = true;
        }

        $changed = $this->normalizeAliasPage(
            $page,
            $alias,
            $slug,
            $url,
            $name,
            $type,
            $template,
            $title,
        );

        $this->em->persist($page);
        $this->em->flush();

        return [$page, $created, $changed];
    }

    private function normalizeAliasPage(
        SonataPagePage $page,
        string $alias,
        string $slug,
        string $url,
        string $name,
        string $type,
        string $template,
        ?string $title = null,
    ): bool {
        $changed = false;

        if ('page_slug' !== $page->getRouteName()) {
            $page->setRouteName('page_slug');
            $changed = true;
        }
        if ($page->getName() !== $name) {
            $page->setName($name);
            $changed = true;
        }
        $title ??= $name;
        if ($page->getTitle() !== $title) {
            $page->setTitle($title);
            $changed = true;
        }
        if ($page->getSlug() !== $slug) {
            $page->setSlug($slug);
            $changed = true;
        }
        if ($page->getUrl() !== $url) {
            $page->setUrl($url);
            $changed = true;
        }
        if (null === $page->getMetaDescription()) {
            $page->setMetaDescription($title);
            $changed = true;
        }
        if (!$page->getEnabled()) {
            $page->setEnabled(true);
            $changed = true;
        }
        if (!$page->getDecorate()) {
            $page->setDecorate(true);
            $changed = true;
        }
        if ($page->getType() !== $type) {
            $page->setType($type);
            $changed = true;
        }
        if ('GET|POST|HEAD|DELETE|PUT' !== $page->getRequestMethod()) {
            $page->setRequestMethod('GET|POST|HEAD|DELETE|PUT');
            $changed = true;
        }
        if ($page->getTemplateCode() !== $template) {
            $page->setTemplateCode($template);
            $changed = true;
        }
        if ($page->getPageAlias() !== $alias) {
            $page->setPageAlias($alias);
            $changed = true;
        }

        return $changed;
    }
}
