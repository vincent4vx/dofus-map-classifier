<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

/**
 * Area of an image, in pixels
 */
final readonly class Rectangle
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}
}
