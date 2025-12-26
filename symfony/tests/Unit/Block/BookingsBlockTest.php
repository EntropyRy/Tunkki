<?php

declare(strict_types=1);

namespace App\Tests\Unit\Block;

use App\Block\BookingsBlock;
use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * @covers \App\Block\BookingsBlock
 */
final class BookingsBlockTest extends TestCase
{
    public function testGetName(): void
    {
        $twig = $this->createStub(Environment::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $block = new BookingsBlock($twig, $em);

        $this->assertSame('Bookings Block', $block->getName());
    }

    public function testConfigureSettings(): void
    {
        $twig = $this->createStub(Environment::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $block = new BookingsBlock($twig, $em);
        $resolver = new OptionsResolver();

        $block->configureSettings($resolver);
        $settings = $resolver->resolve([]);

        $this->assertArrayHasKey('position', $settings);
        $this->assertArrayHasKey('template', $settings);
        $this->assertSame('1', $settings['position']);
        $this->assertSame('block/bookings.html.twig', $settings['template']);
    }

    public function testExecuteQueriesActiveBookings(): void
    {
        $twig = $this->createMock(Environment::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $blockContext = $this->createMock(BlockContextInterface::class);
        $blockModel = $this->createStub(BlockInterface::class);

        $booking1 = $this->createStub(Booking::class);
        $booking2 = $this->createStub(Booking::class);
        $bookings = [$booking1, $booking2];

        $em->expects($this->once())
            ->method('getRepository')
            ->with(Booking::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findBy')
            ->with(
                [
                    'itemsReturned' => false,
                    'cancelled' => false,
                ],
                [
                    'bookingDate' => 'DESC',
                ]
            )
            ->willReturn($bookings);

        $blockContext->expects($this->once())
            ->method('getTemplate')
            ->willReturn('block/bookings.html.twig');

        $blockContext->expects($this->once())
            ->method('getBlock')
            ->willReturn($blockModel);

        $twig->expects($this->once())
            ->method('render')
            ->with(
                'block/bookings.html.twig',
                [
                    'block' => $blockModel,
                    'bookings' => $bookings,
                ]
            )
            ->willReturn('<div>Bookings</div>');

        $block = new BookingsBlock($twig, $em);
        $response = $block->execute($blockContext);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<div>Bookings</div>', $response->getContent());
    }

    public function testGetBlockMetadata(): void
    {
        $twig = $this->createStub(Environment::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $block = new BookingsBlock($twig, $em);

        $metadata = $block->getBlockMetadata();

        $this->assertSame('Bookings Block', $metadata->getTitle());
        $this->assertSame(['class' => 'fa fa-book'], $metadata->getOptions());
    }
}
