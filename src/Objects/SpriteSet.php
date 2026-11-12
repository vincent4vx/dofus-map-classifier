<?php

declare(strict_types=1);

namespace MapClassifier\Objects;

/**
 * Sprites files set: numbers are only unique inside a set
 */
enum SpriteSet: string
{
    /** Ground sprites, from clips/gfx/g*.swf */
    case Grounds = 'grounds';

    /** Object sprites, from clips/gfx/o*.swf */
    case Objects = 'objects';
}
