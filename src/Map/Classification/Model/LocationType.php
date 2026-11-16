<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

/**
 * Kind of place represented by the map
 */
enum LocationType: string
{
    case City = 'city';
    case Village = 'village';
    case Road = 'road';
    case Wild = 'wild';
    case Dungeon = 'dungeon';
    case House = 'house';
    case Shop = 'shop';
    case Temple = 'temple';
    case Mine = 'mine';
    case Arena = 'arena';
    case Other = 'other';

    public function description(): string
    {
        return match ($this) {
            self::City => 'streets and buildings of a large town',
            self::Village => 'a few houses, small settlement',
            self::Road => 'path or road crossing a mostly natural area',
            self::Wild => 'natural area without notable construction',
            self::Dungeon => 'hostile closed area, lair, monster den',
            self::House => 'inside of a house or private building',
            self::Shop => 'inside of a shop, workshop, tavern or bank',
            self::Temple => 'temple, sanctuary, sacred place',
            self::Mine => 'mine galleries, quarry',
            self::Arena => 'arena or area dedicated to fights',
            self::Other => 'none of the above',
        };
    }
}
