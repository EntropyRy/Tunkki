<?php

declare(strict_types=1);

namespace App\Tests\Unit\PageService;

use App\PageService\FrontPage;
use App\Repository\EventArtistInfoRepository;
use App\Repository\EventRepository;
use PHPUnit\Framework\TestCase;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FrontPageTest extends TestCase
{
    public function testExecuteAddsSeoMetaFromPage(): void
    {
        $templateManager = $this->createMock(TemplateManagerInterface::class);
        $eventArtistRepo = $this->createStub(EventArtistInfoRepository::class);
        $eventRepo = $this->createStub(EventRepository::class);
        $seoPage = $this->createStub(SeoPageInterface::class);

        $eventArtistRepo->method('findOnePublicEventArtistInfo')->willReturn(null);
        $eventRepo->method('getFutureEvents')->willReturn([]);
        $eventRepo->method('getUnpublishedFutureEvents')->willReturn([]);
        $eventRepo->method('findOneEventByType')->with('announcement')->willReturn(null);

        $page = $this->createStub(PageInterface::class);
        $page->method('getTitle')->willReturn('Front Page');
        $page->method('getMetaDescription')->willReturn('Front page description');
        $page->method('getMetaKeyword')->willReturn('frontpage,entropy');
        $page->method('getTemplateCode')->willReturn('frontpage');

        $response = new Response('ok');
        $templateManager
            ->expects($this->once())
            ->method('renderResponse')
            ->with(
                'frontpage',
                $this->callback(
                    static fn (array $parameters): bool => \array_key_exists('events', $parameters)
                        && \array_key_exists('info', $parameters),
                ),
                $response,
            )
            ->willReturn($response);

        $seoPage->method('setTitle')->willReturn($seoPage);
        $seoPage->method('addMeta')->willReturn($seoPage);
        $seoPage->method('addHtmlAttributes')->willReturn($seoPage);

        $service = new FrontPage(
            'Front Page',
            $templateManager,
            $eventArtistRepo,
            $eventRepo,
            $seoPage,
        );

        $result = $service->execute($page, Request::create('/'), [], $response);
        $this->assertSame($response, $result);
    }
}
