<?php

declare(strict_types=1);

namespace App\Tests\Unit\Block\Rental;

use App\Block\Rental\RentalFormBlock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * @covers \App\Block\Rental\RentalFormBlock
 */
final class RentalFormBlockTest extends TestCase
{
    public function testGetName(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new RentalFormBlock($twig);

        $this->assertSame('Rental Form Block', $block->getName());
    }

    public function testConfigureSettings(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new RentalFormBlock($twig);
        $resolver = new OptionsResolver();

        $block->configureSettings($resolver);
        $settings = $resolver->resolve([]);

        $this->assertArrayHasKey('position', $settings);
        $this->assertArrayHasKey('template', $settings);
        $this->assertSame('1', $settings['position']);
        $this->assertSame('block/rental_form.html.twig', $settings['template']);
    }

    public function testGetMetadata(): void
    {
        $twig = $this->createStub(Environment::class);
        $block = new RentalFormBlock($twig);

        $metadata = $block->getMetadata();

        $this->assertSame('Rental Form Block', $metadata->getTitle());
        $this->assertSame(['class' => 'fa fa-file-text'], $metadata->getOptions());
    }
}
