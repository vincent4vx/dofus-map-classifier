<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use Arakne\MapParser\Parser\Cell;
use Arakne\MapParser\Parser\LayerObjectInterface;

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

    /**
     * Sprite drawn on the cell on this layer, or null for the background (not drawn on cells)
     */
    public function object(Cell $cell): ?LayerObjectInterface
    {
        return match ($this) {
            self::Background => null,
            self::Ground => $cell->ground,
            self::Layer1 => $cell->layer1,
            self::Layer2 => $cell->layer2,
        };
    }
}
