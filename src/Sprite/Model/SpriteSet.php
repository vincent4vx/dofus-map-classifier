<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

/**
 * Sprites files set: numbers are only unique inside a set
 */
enum SpriteSet: string
{
    /** Ground sprites, from clips/gfx/g*.swf */
    case Grounds = 'grounds';

    /** Object sprites, from clips/gfx/o*.swf */
    case Objects = 'objects';

    /**
     * Name of a sprite of this set, with the prefix of the set (e.g. "o345", "g10")
     */
    public function spriteName(int $id): string
    {
        return ($this === self::Grounds ? 'g' : 'o') . $id;
    }
}
