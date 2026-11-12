<?php

declare(strict_types=1);

namespace MapClassifier\ObjectType;

/**
 * Closed list of sprite categories, to ease search
 * The precise type is given by the free "name" and "keywords" fields
 */
enum ObjectCategory: string
{
    // Vegetation
    case Tree = 'tree';
    case Bush = 'bush';
    case Grass = 'grass';
    case Flower = 'flower';
    case Plant = 'plant';
    case Crop = 'crop';
    case Mushroom = 'mushroom';

    // Minerals and relief
    case Rock = 'rock';
    case Cliff = 'cliff';
    case Water = 'water';

    // Ground tiles
    case Ground = 'ground';
    case Path = 'path';
    case Floor = 'floor';

    // Buildings
    case Building = 'building';
    case Wall = 'wall';
    case Roof = 'roof';
    case Door = 'door';
    case Window = 'window';
    case Stairs = 'stairs';
    case Bridge = 'bridge';
    case Fence = 'fence';

    // Objects
    case Furniture = 'furniture';
    case Container = 'container';
    case Light = 'light';
    case Statue = 'statue';
    case Tomb = 'tomb';
    case Sign = 'sign';
    case Tool = 'tool';
    case Vehicle = 'vehicle';
    case Decoration = 'decoration';
    case Remains = 'remains';
    case Creature = 'creature';

    // Visual effects and game elements
    case Shadow = 'shadow';
    case Effect = 'effect';
    case Marker = 'marker';

    case Other = 'other';

    public function description(): string
    {
        return match ($this) {
            self::Tree => 'tree, palm tree, dead tree, tree trunk or stump',
            self::Bush => 'bush, shrub, hedge',
            self::Grass => 'grass tuft, tall grass, reeds',
            self::Flower => 'flowers, flower bed',
            self::Plant => 'other vegetation: fern, vine, bamboo, cactus, aquatic plant, moss',
            self::Crop => 'cultivated plants: wheat, cereals, vegetables',
            self::Mushroom => 'mushrooms',
            self::Rock => 'rock, boulder, stones, pebbles, crystal',
            self::Cliff => 'cliff, rock wall, relief edge, chasm border',
            self::Water => 'water surface, waterfall, lava, puddle, water edge',
            self::Ground => 'natural ground tile: grass, dirt, sand, snow, mud',
            self::Path => 'outdoor path, road, paving, stepping stones',
            self::Floor => 'indoor floor tile: planks, tiles, carpet',
            self::Building => 'whole building or large part of it: house, tower, tent, hut',
            self::Wall => 'wall, rampart, wall section',
            self::Roof => 'roof or roof part',
            self::Door => 'door, gate, portal',
            self::Window => 'window',
            self::Stairs => 'stairs, ladder, steps',
            self::Bridge => 'bridge, walkway, pontoon',
            self::Fence => 'fence, barrier, railing, palisade',
            self::Furniture => 'table, chair, bed, shelf, counter, carpet',
            self::Container => 'chest, barrel, crate, bag, jar, basket',
            self::Light => 'lamp, torch, candle, lantern, brazier, fire',
            self::Statue => 'statue, monument, totem, pillar, column',
            self::Tomb => 'tombstone, grave, cross, coffin',
            self::Sign => 'signpost, banner, flag, poster',
            self::Tool => 'tool, weapon, workshop equipment, anvil, well',
            self::Vehicle => 'boat, cart, wagon',
            self::Decoration => 'other small decorative object: pottery, books, bottles, food',
            self::Remains => 'bones, skull, debris, ruins, rubble',
            self::Creature => 'static animal or character drawn in the decor',
            self::Shadow => 'shadow or dark spot only',
            self::Effect => 'visual effect: light rays, fog, sparkles, smoke, glow',
            self::Marker => 'game marker or technical sprite: cell marker, teleport marker, invisible or abstract shape',
            self::Other => 'none of the above',
        };
    }
}
