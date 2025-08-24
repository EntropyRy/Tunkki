<?php
// src/Twig/Components/ProgressiveImageComponent.php

namespace App\Twig\Components;

use Sonata\MediaBundle\Model\MediaInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class ProgressiveImage
{
    public MediaInterface $media;
    public array $sizes = [];
    public ?string $placeholder = null;
    public string $class = "";
    public string $containerClass = "";
    public string $placeholderClass = "";
    public string $pictureClass = "";
    public string $imageClass = "";
    public string $alt = "";
    public string $title = "";
    public bool $lazy = true;
    public array $placeholderAttributes = [];
    public array $pictureAttributes = [];
    public array $imgAttributes = [];
    public array $containerAttributes = [];

    public function mount(
        MediaInterface $media,
        array $sizes = [],
        ?string $placeholder = null,
        string $class = "",
        string $containerClass = "",
        string $placeholderClass = "",
        string $pictureClass = "",
        string $imageClass = "",
        string $alt = "",
        string $title = "",
        bool $lazy = true,
        array $placeholderAttributes = [],
        array $pictureAttributes = [],
        array $imgAttributes = [],
        array $containerAttributes = [],
    ): void {
        $this->media = $media;
        $this->sizes = $sizes ?: $this->getDefaultSizes();
        $this->placeholder = $placeholder;

        // Handle class parameter
        $this->class = $class;
        $this->containerClass = $containerClass ?: $class;
        $this->placeholderClass = $placeholderClass;
        $this->pictureClass = $pictureClass;
        $this->imageClass = $imageClass ?: $class;

        // Handle alt and title with fallbacks
        $this->alt = $alt ?: $media->getName() ?: "Image";
        $this->title =
            $title ?: $media->getDescription() ?: $media->getName() ?: "";

        $this->lazy = $lazy;
        $this->placeholderAttributes = $placeholderAttributes;
        $this->pictureAttributes = $pictureAttributes;
        $this->imgAttributes = $imgAttributes;
        $this->containerAttributes = $containerAttributes;
    }

    private function getDefaultSizes(): array
    {
        return [
            "small" => "(max-width: 480px)",
            "medium" => "(max-width: 768px)",
            "large" => "(min-width: 769px)",
        ];
    }

    public function getPlaceholderSrc(): string
    {
        if ($this->placeholder) {
            return $this->placeholder;
        }

        return "data:image/svg+xml;base64," .
            base64_encode(
                '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
                <rect width="100%" height="100%" fill="#f0f0f0"/>
                <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999">Loading...</text>
            </svg>',
            );
    }

    public function getContainerClasses(): string
    {
        $classes = ["progressive-media-container"];

        if ($this->containerClass !== "" && $this->containerClass !== "0") {
            $classes[] = $this->containerClass;
        }

        return implode(" ", $classes);
    }

    public function getPlaceholderClasses(): string
    {
        $classes = ["progressive-placeholder"];

        if ($this->placeholderClass !== "" && $this->placeholderClass !== "0") {
            $classes[] = $this->placeholderClass;
        }

        return implode(" ", $classes);
    }

    public function getPictureClasses(): string
    {
        $classes = ["progressive-picture"];

        if ($this->pictureClass !== "" && $this->pictureClass !== "0") {
            $classes[] = $this->pictureClass;
        }

        return implode(" ", $classes);
    }

    public function getImageClasses(): string
    {
        $classes = ["progressive-image"];

        if ($this->imageClass !== "" && $this->imageClass !== "0") {
            $classes[] = $this->imageClass;
        }

        return implode(" ", $classes);
    }
}
