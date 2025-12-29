<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use App\Twig\LocalizedUrlExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sonata\PageBundle\CmsManager\CmsManagerInterface;
use Sonata\PageBundle\CmsManager\CmsManagerSelectorInterface;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\TwigFunction;

/**
 * Unit tests for LocalizedUrlExtension with full branch coverage.
 */
final class LocalizedUrlExtensionTest extends TestCase
{
    private MockObject&RouterInterface $router;
    private MockObject&RequestStack $requestStack;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&CmsManagerSelectorInterface $cmsManagerSelector;
    private LocalizedUrlExtension $extension;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cmsManagerSelector = $this->createMock(CmsManagerSelectorInterface::class);

        $this->extension = new LocalizedUrlExtension(
            $this->router,
            $this->requestStack,
            $this->entityManager,
            $this->cmsManagerSelector,
        );
    }

    public function testGetFunctionsReturnsLocalizedUrlFunction(): void
    {
        $functions = $this->extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertInstanceOf(TwigFunction::class, $functions[0]);
        self::assertSame('localized_url', $functions[0]->getName());
    }

    public function testGetLocalizedUrlWithoutRequestReturnsRoot(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $result = $this->extension->getLocalizedUrl('en');

        self::assertSame('/', $result);
    }

    public function testGetLocalizedUrlForRootPathReturnsEnglishPrefix(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->extension->getLocalizedUrl('en');

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlForRootPathReturnsFinnishRoot(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->extension->getLocalizedUrl('fi');

        self::assertSame('/', $result);
    }

    public function testGetLocalizedUrlForEnglishRootPathReturnsEnglish(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/en']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->extension->getLocalizedUrl('en');

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlForEnglishRootPathReturnsFinnish(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/en']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->extension->getLocalizedUrl('fi');

        self::assertSame('/', $result);
    }

    public function testGetLocalizedUrlForSymfonyRouteGeneratesLocalizedRoute(): void
    {
        $request = new Request([], [], ['_route' => 'app_event_show', '_route_params' => ['id' => 123]], [], [], ['REQUEST_URI' => '/events/123']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->router->method('generate')
            ->with('app_event_show.en', ['id' => 123])
            ->willReturn('/en/events/123');

        $result = $this->extension->getLocalizedUrl('en');

        self::assertSame('/en/events/123', $result);
    }

    public function testGetLocalizedUrlForPageSlugRouteFallsBackToLocalePrefix(): void
    {
        $request = new Request([], [], ['_route' => 'page_slug'], [], [], ['REQUEST_URI' => '/some-page']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->extension->getLocalizedUrl('en');

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlForPageSlugRouteFinnishFallback(): void
    {
        $request = new Request([], [], ['_route' => 'page_slug'], [], [], ['REQUEST_URI' => '/some-page']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->extension->getLocalizedUrl('fi');

        self::assertSame('/', $result);
    }

    public function testGetLocalizedUrlWithIntegerPageIdResolvesPage(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // The page that will be looked up
        $page = $this->createMock(PageInterface::class);
        $page->method('getPageAlias')->willReturn(null);
        $page->method('getSite')->willReturn(null);
        $page->method('getId')->willReturn(42);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(42)->willReturn($page);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(fn (string $class) => match ($class) {
                SonataPagePage::class => $repository,
                Menu::class => $menuRepository,
                default => throw new \RuntimeException("Unexpected class: $class"),
            });

        $result = $this->extension->getLocalizedUrl('en', 42);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlWithIntegerPageIdNotFoundFallsBack(): void
    {
        $request = new Request([], [], ['_route' => 'page_slug'], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(999)->willReturn(null);
        $this->entityManager->method('getRepository')
            ->with(SonataPagePage::class)
            ->willReturn($repository);

        $result = $this->extension->getLocalizedUrl('en', 999);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageViaTechnicalAlias(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page with technical alias
        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services_fi');
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // Target page in English
        $targetSite = $this->createSiteMock('en', '/en');
        $targetPage = $this->createMock(PageInterface::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn('/services');
        $targetPage->method('getSite')->willReturn($targetSite);

        // CmsManager will find the target page by alias
        $cmsManager = $this->createMock(CmsManagerInterface::class);
        $cmsManager->method('getPageByPageAlias')
            ->with($this->isInstanceOf(SiteInterface::class), '_page_alias_services_en')
            ->willReturn($targetPage);
        $this->cmsManagerSelector->method('retrieve')->willReturn($cmsManager);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en/services', $result);
    }

    public function testGetLocalizedUrlFromPageViaTechnicalAliasTargetPageDisabled(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page with technical alias
        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services_fi');
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // Target page is disabled
        $targetPage = $this->createMock(PageInterface::class);
        $targetPage->method('getEnabled')->willReturn(false);

        $cmsManager = $this->createMock(CmsManagerInterface::class);
        $cmsManager->method('getPageByPageAlias')->willReturn($targetPage);
        $this->cmsManagerSelector->method('retrieve')->willReturn($cmsManager);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageViaTechnicalAliasTargetPageNoUrl(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page with technical alias
        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services_fi');
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // Target page has no URL
        $targetPage = $this->createMock(PageInterface::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn(null);
        $targetPage->method('getSite')->willReturn($this->createSiteMock('en', '/en'));

        $cmsManager = $this->createMock(CmsManagerInterface::class);
        $cmsManager->method('getPageByPageAlias')->willReturn($targetPage);
        $this->cmsManagerSelector->method('retrieve')->willReturn($cmsManager);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageViaMenuLookup(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(1);

        // Target page in English
        $targetSite = $this->createSiteMock('en', '/en');
        $targetPage = $this->createMock(SonataPagePage::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn('/services');
        $targetPage->method('getSite')->willReturn($targetSite);

        // Menu found via direct comparison
        $menu = $this->createMock(Menu::class);
        $menu->method('getPageByLang')->with('en')->willReturn($targetPage);

        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')
            ->willReturnCallback(fn (array $criteria) => isset($criteria['pageFi']) ? $menu : null);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en/services', $result);
    }

    public function testGetLocalizedUrlFromPageViaMenuLookupPageEn(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(1);

        // Target page in Finnish
        $targetSite = $this->createSiteMock('fi', '');
        $targetPage = $this->createMock(SonataPagePage::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn('/palvelut');
        $targetPage->method('getSite')->willReturn($targetSite);

        // Menu found via pageEn comparison (second findOneBy call)
        $menu = $this->createMock(Menu::class);
        $menu->method('getPageByLang')->with('fi')->willReturn($targetPage);

        $menuRepository = $this->createMock(EntityRepository::class);
        $callCount = 0;
        $menuRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use (&$callCount, $menu) {
                ++$callCount;
                // First call is pageFi, returns null
                // Second call is pageEn, returns menu
                return 2 === $callCount ? $menu : null;
            });

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('fi', $sourcePage);

        self::assertSame('/palvelut', $result);
    }

    public function testGetLocalizedUrlFromPageViaMenuLookupById(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(42);

        // Target page in English
        $targetSite = $this->createSiteMock('en', '/en');
        $targetPage = $this->createMock(SonataPagePage::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn('/services');
        $targetPage->method('getSite')->willReturn($targetSite);

        // Menu found via ID query
        $menu = $this->createMock(Menu::class);
        $menu->method('getPageByLang')->with('en')->willReturn($targetPage);

        $menuRepository = $this->createMock(EntityRepository::class);
        // Direct comparison returns null (simulating proxy object issue)
        $menuRepository->method('findOneBy')->willReturn(null);

        // ID-based query returns menu
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn($menu);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en/services', $result);
    }

    public function testGetLocalizedUrlFromPageMenuLookupTargetDisabled(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(1);

        // Target page is disabled
        $targetPage = $this->createMock(SonataPagePage::class);
        $targetPage->method('getEnabled')->willReturn(false);

        // Menu found
        $menu = $this->createMock(Menu::class);
        $menu->method('getPageByLang')->with('en')->willReturn($targetPage);

        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')
            ->willReturnCallback(fn (array $criteria) => isset($criteria['pageFi']) ? $menu : null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testFindPageByAliasWithNoRequest(): void
    {
        // First call returns a request (for getLocalizedUrl), then null (for findPageByAlias)
        $this->requestStack->method('getCurrentRequest')
            ->willReturnOnConsecutiveCalls(
                new Request([], [], [], [], [], ['REQUEST_URI' => '/services']),
                null
            );

        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services_fi');
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testFindPageByAliasWithNoSiteAttribute(): void
    {
        // Request without site attribute
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services_fi');
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testFindPageByAliasWithCmsManagerException(): void
    {
        $site = $this->createSiteMock('fi');
        $request = new Request([], [], ['site' => $site], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services_fi');
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // CmsManager throws exception
        $cmsManager = $this->createMock(CmsManagerInterface::class);
        $cmsManager->method('getPageByPageAlias')->willThrowException(new \RuntimeException('Page not found'));
        $this->cmsManagerSelector->method('retrieve')->willReturn($cmsManager);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testFindPageThroughMenuDirectComparisonThrows(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(42);

        // Direct comparison throws (simulating proxy object issue)
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willThrowException(new \RuntimeException('Proxy comparison error'));

        // ID-based query returns null
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testFindPageThroughMenuIdQueryThrows(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(42);

        // Direct comparison returns null
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);

        // ID-based query throws
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willThrowException(new \RuntimeException('Query error'));
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageWithNullPageId(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page with null ID (covers getPageIdSafely returning 'unknown')
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(null);

        // Menu lookup won't find anything (ID is 'unknown', not numeric)
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        // createQueryBuilder should not be called since ctype_digit('unknown') is false

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageAliasNotEndingWithLocaleSuffix(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page with alias that doesn't end with locale suffix
        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services'); // No _fi suffix
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageTargetHasNoSite(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page with technical alias
        $sourceSite = $this->createSiteMock('fi');
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn('_page_alias_services_fi');
        $sourcePage->method('getSite')->willReturn($sourceSite);
        $sourcePage->method('getId')->willReturn(1);

        // Target page enabled but no site
        $targetPage = $this->createMock(PageInterface::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn('/services');
        $targetPage->method('getSite')->willReturn(null);

        $cmsManager = $this->createMock(CmsManagerInterface::class);
        $cmsManager->method('getPageByPageAlias')->willReturn($targetPage);
        $this->cmsManagerSelector->method('retrieve')->willReturn($cmsManager);

        // Menu lookup won't find anything
        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageMenuTargetHasNoSite(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(1);

        // Target page enabled but no site
        $targetPage = $this->createMock(SonataPagePage::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn('/services');
        $targetPage->method('getSite')->willReturn(null);

        // Menu found
        $menu = $this->createMock(Menu::class);
        $menu->method('getPageByLang')->with('en')->willReturn($targetPage);

        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')
            ->willReturnCallback(fn (array $criteria) => isset($criteria['pageFi']) ? $menu : null);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    public function testGetLocalizedUrlFromPageMenuTargetHasNoUrl(): void
    {
        $request = new Request([], [], ['site' => $this->createSiteMock('fi')], [], [], ['REQUEST_URI' => '/services']);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Source page without technical alias
        $sourcePage = $this->createMock(PageInterface::class);
        $sourcePage->method('getPageAlias')->willReturn(null);
        $sourcePage->method('getSite')->willReturn(null);
        $sourcePage->method('getId')->willReturn(1);

        // Target page enabled but no URL
        $targetPage = $this->createMock(SonataPagePage::class);
        $targetPage->method('getEnabled')->willReturn(true);
        $targetPage->method('getUrl')->willReturn(null);
        $targetPage->method('getSite')->willReturn($this->createSiteMock('en', '/en'));

        // Menu found
        $menu = $this->createMock(Menu::class);
        $menu->method('getPageByLang')->with('en')->willReturn($targetPage);

        $menuRepository = $this->createMock(EntityRepository::class);
        $menuRepository->method('findOneBy')
            ->willReturnCallback(fn (array $criteria) => isset($criteria['pageFi']) ? $menu : null);

        $this->entityManager->method('getRepository')
            ->with(Menu::class)
            ->willReturn($menuRepository);

        $result = $this->extension->getLocalizedUrl('en', $sourcePage);

        self::assertSame('/en', $result);
    }

    /**
     * @return MockObject&SiteInterface
     */
    private function createSiteMock(string $locale, string $relativePath = ''): MockObject&SiteInterface
    {
        $site = $this->createMock(SiteInterface::class);
        $site->method('getLocale')->willReturn($locale);
        $site->method('getRelativePath')->willReturn($relativePath);

        return $site;
    }
}
