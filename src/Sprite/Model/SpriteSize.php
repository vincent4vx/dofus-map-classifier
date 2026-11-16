<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

/**
 * Visible size of a sprite, in game pixels (one map cell is 53 x 27 pixels)
 */
final readonly class SpriteSize
{
    public function __construct(
        public float $width,
        public float $height,
    ) {}
}
