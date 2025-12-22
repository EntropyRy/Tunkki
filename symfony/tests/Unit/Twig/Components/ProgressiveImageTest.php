<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components;

use App\Twig\Components\ProgressiveImage;
use PHPUnit\Framework\TestCase;
use Sonata\MediaBundle\Model\MediaInterface;

final class ProgressiveImageTest extends TestCase
{
    public function testMountAppliesDefaults(): void
    {
        $media = $this->createStub(MediaInterface::class);
        $media->method('getName')->willReturn('Hero Graphic');
        $media->method('getDescription')->willReturn('Gradient background');

        $component = new ProgressiveImage();
        $component->mount($media);

        self::assertSame('Hero Graphic', $component->alt);
        self::assertSame('Gradient background', $component->title);
        self::assertTrue($component->lazy);
        self::assertSame(['small', 'medium', 'large'], array_keys($component->sizes));

        $placeholder = $component->getPlaceholderSrc();
        self::assertStringStartsWith('data:image/svg+xml;base64,', $placeholder);
        self::assertStringContainsString('Loading...', base64_decode(str_replace('data:image/svg+xml;base64,', '', $placeholder)));

        self::assertSame('progressive-media-container', $component->getContainerClasses());
        self::assertSame('progressive-placeholder', $component->getPlaceholderClasses());
        self::assertSame('progressive-picture', $component->getPictureClasses());
        self::assertSame('progressive-image', $component->getImageClasses());
    }

    public function testMountUsesCustomClassesAndPlaceholder(): void
    {
        $media = $this->createStub(MediaInterface::class);
        $media->method('getName')->willReturn(null);
        $media->method('getDescription')->willReturn(null);

        $component = new ProgressiveImage();
        $component->mount(
            media: $media,
            sizes: ['xl' => '(min-width: 1200px)'],
            placeholder: '/images/placeholder.jpg',
            class: 'rounded',
            containerClass: 'wrapper',
            placeholderClass: 'blurred',
            pictureClass: 'picture-frame',
            imageClass: 'full-width',
            alt: 'Custom alt',
            title: 'Custom title',
            lazy: false,
            pictureAttributes: ['data-test' => 'picture'],
            imgAttributes: ['loading' => 'eager'],
            containerAttributes: ['data-test' => 'container'],
        );

        self::assertSame('/images/placeholder.jpg', $component->getPlaceholderSrc());
        self::assertSame('Custom alt', $component->alt);
        self::assertSame('Custom title', $component->title);
        self::assertFalse($component->lazy);
        $containerClasses = $component->getContainerClasses();
        self::assertStringContainsString('progressive-media-container', $containerClasses);
        self::assertStringContainsString('wrapper', $containerClasses);
        self::assertStringContainsString('progressive-placeholder', $component->getPlaceholderClasses());
        self::assertStringContainsString('blurred', $component->getPlaceholderClasses());
        self::assertStringContainsString('progressive-picture', $component->getPictureClasses());
        self::assertStringContainsString('picture-frame', $component->getPictureClasses());
        self::assertStringContainsString('full-width', $component->getImageClasses());
        self::assertSame(['xl' => '(min-width: 1200px)'], $component->sizes);
        self::assertSame(['data-test' => 'picture'], $component->pictureAttributes);
        self::assertSame(['loading' => 'eager'], $component->imgAttributes);
        self::assertSame(['data-test' => 'container'], $component->containerAttributes);
    }
}
