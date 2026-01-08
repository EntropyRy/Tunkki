<?php

declare(strict_types=1);

namespace App\Tests\Unit\PageService;

use App\PageService\EventsPage;
use App\Repository\EventRepository;
use PHPUnit\Framework\TestCase;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class EventsPageTest extends TestCase
{
    public function testExecuteAddsSeoMetaFromPage(): void
    {
        $templateManager = $this->createStub(TemplateManagerInterface::class);
        $eventRepository = $this->createStub(EventRepository::class);
        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $seoPage = $this->createStub(SeoPageInterface::class);
        $security = $this->createStub(Security::class);

        $security->method('getUser')->willReturn(null);
        $eventRepository->method('findPublicEventsByNotType')->with('announcement')->willReturn([]);
        $assetMapper->method('getPublicPath')->with('images/header-logo.svg')->willReturn('/images/header-logo.svg');

        $seoPage->method('setTitle')->willReturn($seoPage);
        $seoPage->method('addMeta')->willReturn($seoPage);
        $seoPage->method('addHtmlAttributes')->willReturn($seoPage);

        $page = $this->createStub(PageInterface::class);
        $page->method('getTitle')->willReturn('Events');
        $page->method('getMetaDescription')->willReturn('Events page description');
        $page->method('getMetaKeyword')->willReturn('events,entropy');
        $page->method('getTemplateCode')->willReturn('events');

        $response = new Response('ok');
        $templateManager->method('renderResponse')->willReturn($response);

        $service = new EventsPage(
            'Events',
            $templateManager,
            $eventRepository,
            $assetMapper,
            $seoPage,
            $security,
        );

        $request = Request::create('https://localhost/tapahtumat');
        $result = $service->execute($page, $request, [], $response);

        $this->assertSame($response, $result);
    }
}
