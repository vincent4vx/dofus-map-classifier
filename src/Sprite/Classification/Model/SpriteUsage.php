<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model;

use MapClassifier\Sprite\Model\Layer;

use function array_values;
use function array_filter;

/**
 * How a sprite is used on the maps, to place it when generating maps
 * The possible usages depend on the layer of the sprite: only the usages of its main layer are asked to the model.
 * Backgrounds have no usage.
 */
enum SpriteUsage: string
{
    // Ground: tile covering one cell
    case Fill = 'fill';
    case Variation = 'variation';

    // Layer1: drawn flat on the ground, below characters
    case Ground = 'ground';
    case SmallObject = 'small_object';
    case Shadow = 'shadow';

    // Layer2: standing on the ground, drawn above the floor
    case BigObject = 'big_object';
    case Wall = 'wall';
    case Building = 'building';
    case Decoration = 'decoration';

    public function layer(): Layer
    {
        return match ($this) {
            self::Fill, self::Variation => Layer::Ground,
            self::Ground, self::SmallObject, self::Shadow => Layer::Layer1,
            self::BigObject, self::Wall, self::Building, self::Decoration => Layer::Layer2,
        };
    }

    /**
     * Description given to the model
     */
    public function description(): string
    {
        return match ($this) {
            self::Fill => 'base tile repeated to cover an area (grass, sand, dirt, water, wooden floor, paving…), including the tiles of a pattern drawn on several cells (carpet, mosaic) and the transition tiles',
            self::Variation => 'variant of a base tile with a detail (flowers, cracks, stains, small stones…), placed sparsely among the base tiles to break the repetition',
            self::Ground => 'detail painted on the ground, part of the floor surface: path, road, grass or dirt patch, carpet, puddle, ground border or pattern',
            self::SmallObject => 'small object lying on the ground: stones, pebbles, small plants and flowers, bones, leaves, small items',
            self::Shadow => 'shadow or light spot cast on the ground, usually under or next to another element',
            self::BigObject => 'large standalone element, alone in its place: tree, big rock, statue, big furniture, well, cart, workshop, resource',
            self::Wall => 'part of a linear barrier, repeated along a line: wall, rampart, fence, palisade, hedge, cliff side, railing',
            self::Building => 'part of a building: house or tower wall, roof, door, window, stairs, pillar, arch',
            self::Decoration => 'small decoration placed on or next to other elements: small plant, lamp, sign, pot, book, tableware, small furniture, wall hanging',
        };
    }

    /**
     * Usages of the sprites of the layer, empty for the layers without usage (background, ground)
     *
     * @return list<self>
     */
    public static function of(Layer $layer): array
    {
        return array_values(array_filter(self::cases(), static fn (self $usage) => $usage->layer() === $layer));
    }
}
