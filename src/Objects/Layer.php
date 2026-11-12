<?php

declare(strict_types=1);

namespace MapClassifier\Objects;

/**
 * Layer of a map where a sprite is used
 */
enum Layer: string
{
    case Background = 'background';
    case Ground = 'ground';
    case Layer1 = 'layer1';
    case Layer2 = 'layer2';

    /**
     * Sprites set used by the layer: background and ground use the ground sprites (g*.swf),
     * layer1 and layer2 use the object sprites (o*.swf). Numbers of both sets are independent.
     */
    public function spriteSet(): SpriteSet
    {
        return match ($this) {
            self::Background, self::Ground => SpriteSet::Grounds,
            self::Layer1, self::Layer2 => SpriteSet::Objects,
        };
    }
}
