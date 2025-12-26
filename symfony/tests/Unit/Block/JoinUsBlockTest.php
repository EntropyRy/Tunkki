<?php

declare(strict_types=1);

namespace App\Tests\Unit\Block;

use App\Block\JoinUsBlock;
use PHPUnit\Framework\TestCase;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * @covers \App\Block\JoinUsBlock
 */
final class JoinUsBlockTest extends TestCase
{
    public function testGetName(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new JoinUsBlock($twig);

        $this->assertSame('Join Us Block', $block->getName());
    }

    public function testConfigureSettings(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new JoinUsBlock($twig);
        $resolver = new OptionsResolver();

        $block->configureSettings($resolver);
        $settings = $resolver->resolve([]);

        $this->assertArrayHasKey('position', $settings);
        $this->assertArrayHasKey('template', $settings);
        $this->assertSame('1', $settings['position']);
        $this->assertSame('member/joinus_block.html.twig', $settings['template']);
    }

    public function testExecuteRendersTemplate(): void
    {
        $twig = $this->createMock(Environment::class);
        $blockContext = $this->createMock(BlockContextInterface::class);
        $blockModel = $this->createStub(BlockInterface::class);

        $blockContext->expects($this->once())
            ->method('getTemplate')
            ->willReturn('member/joinus_block.html.twig');

        $blockContext->expects($this->once())
            ->method('getBlock')
            ->willReturn($blockModel);

        $twig->expects($this->once())
            ->method('render')
            ->with(
                'member/joinus_block.html.twig',
                ['block' => $blockModel]
            )
            ->willReturn('<div>Join Us</div>');

        $block = new JoinUsBlock($twig);
        $response = $block->execute($blockContext);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<div>Join Us</div>', $response->getContent());
    }

    public function testGetMetadata(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new JoinUsBlock($twig);

        $metadata = $block->getMetadata();

        $this->assertSame('Join Us Block', $metadata->getTitle());
        $this->assertSame(['class' => 'fa fa-user'], $metadata->getOptions());
    }
}
