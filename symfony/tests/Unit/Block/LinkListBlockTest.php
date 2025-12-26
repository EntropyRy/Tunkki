<?php

declare(strict_types=1);

namespace App\Tests\Unit\Block;

use App\Block\LinkListBlock;
use PHPUnit\Framework\TestCase;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * @covers \App\Block\LinkListBlock
 */
final class LinkListBlockTest extends TestCase
{
    public function testGetName(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new LinkListBlock($twig);

        $this->assertSame('Link List Block', $block->getName());
    }

    public function testConfigureSettings(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new LinkListBlock($twig);
        $resolver = new OptionsResolver();

        $block->configureSettings($resolver);
        $settings = $resolver->resolve([]);

        $this->assertArrayHasKey('title', $settings);
        $this->assertArrayHasKey('show', $settings);
        $this->assertArrayHasKey('urls', $settings);
        $this->assertArrayHasKey('template', $settings);
        $this->assertNull($settings['title']);
        $this->assertFalse($settings['show']);
        $this->assertNull($settings['urls']);
        $this->assertSame('block/links.html.twig', $settings['template']);
    }

    public function testExecuteRendersTemplateWithSettings(): void
    {
        $twig = $this->createMock(Environment::class);
        $blockContext = $this->createMock(BlockContextInterface::class);
        $blockModel = $this->createStub(BlockInterface::class);

        $settings = [
            'title' => 'Useful Links',
            'show' => 'everybody',
            'urls' => [
                ['title' => 'Link 1', 'url' => 'https://example.com/1'],
                ['title' => 'Link 2', 'url' => 'https://example.com/2'],
            ],
            'template' => 'block/links.html.twig',
        ];

        $blockContext->expects($this->once())
            ->method('getTemplate')
            ->willReturn('block/links.html.twig');

        $blockContext->expects($this->once())
            ->method('getBlock')
            ->willReturn($blockModel);

        $blockContext->expects($this->once())
            ->method('getSettings')
            ->willReturn($settings);

        $twig->expects($this->once())
            ->method('render')
            ->with(
                'block/links.html.twig',
                [
                    'block' => $blockModel,
                    'settings' => $settings,
                ]
            )
            ->willReturn('<div>Links</div>');

        $block = new LinkListBlock($twig);
        $response = $block->execute($blockContext);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<div>Links</div>', $response->getContent());
    }

    public function testGetMetadata(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new LinkListBlock($twig);

        $metadata = $block->getMetadata();

        $this->assertSame('Link List Block', $metadata->getTitle());
        $this->assertSame(['class' => 'fa fa-link'], $metadata->getOptions());
    }
}
