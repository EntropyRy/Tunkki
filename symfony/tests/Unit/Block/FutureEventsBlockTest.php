<?php

declare(strict_types=1);

namespace App\Tests\Unit\Block;

use App\Block\FutureEventsBlock;
use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * @covers \App\Block\FutureEventsBlock
 */
final class FutureEventsBlockTest extends TestCase
{
    public function testGetName(): void
    {
        $twig = $this->createStub(Environment::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $block = new FutureEventsBlock($twig, $em);

        $this->assertSame('Future Events Block', $block->getName());
    }

    public function testConfigureSettings(): void
    {
        $twig = $this->createStub(Environment::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $block = new FutureEventsBlock($twig, $em);
        $resolver = new OptionsResolver();

        $block->configureSettings($resolver);
        $settings = $resolver->resolve([]);

        $this->assertArrayHasKey('template', $settings);
        $this->assertArrayHasKey('box', $settings);
        $this->assertSame('block/future_events.html.twig', $settings['template']);
        $this->assertFalse($settings['box']);
    }

    public function testExecuteQueriesFutureAndUnpublishedEvents(): void
    {
        $twig = $this->createMock(Environment::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EventRepository::class);
        $blockContext = $this->createMock(BlockContextInterface::class);
        $blockModel = $this->createStub(BlockInterface::class);

        $event1 = $this->createStub(Event::class);
        $event2 = $this->createStub(Event::class);
        $futureEvents = [$event1, $event2];

        $event3 = $this->createStub(Event::class);
        $unpublishedEvents = [$event3];

        $settings = ['template' => 'block/future_events.html.twig', 'box' => false];

        $em->expects($this->once())
            ->method('getRepository')
            ->with(Event::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('getFutureEvents')
            ->willReturn($futureEvents);

        $repository->expects($this->once())
            ->method('getUnpublishedFutureEvents')
            ->willReturn($unpublishedEvents);

        $blockContext->expects($this->once())
            ->method('getTemplate')
            ->willReturn('block/future_events.html.twig');

        $blockContext->expects($this->once())
            ->method('getBlock')
            ->willReturn($blockModel);

        $blockContext->expects($this->once())
            ->method('getSettings')
            ->willReturn($settings);

        $twig->expects($this->once())
            ->method('render')
            ->with(
                'block/future_events.html.twig',
                [
                    'block' => $blockModel,
                    'events' => $futureEvents,
                    'unreleased' => $unpublishedEvents,
                    'settings' => $settings,
                ]
            )
            ->willReturn('<div>Future Events</div>');

        $block = new FutureEventsBlock($twig, $em);
        $response = $block->execute($blockContext);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<div>Future Events</div>', $response->getContent());
    }

    public function testGetBlockMetadata(): void
    {
        $twig = $this->createStub(Environment::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $block = new FutureEventsBlock($twig, $em);

        $metadata = $block->getBlockMetadata();

        $this->assertSame('Future Events Block', $metadata->getTitle());
        $this->assertSame(['class' => 'fa fa-bullhorn'], $metadata->getOptions());
    }
}
