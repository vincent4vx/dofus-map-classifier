<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

/**
 * Sprite rendered as PNG
 */
final readonly class RenderedSprite
{
    public function __construct(
        /** PNG data */
        public string $png,
        /** Visible width of the sprite, in game pixels */
        public float $width,
        /** Visible height of the sprite, in game pixels */
        public float $height,
    ) {}
}
