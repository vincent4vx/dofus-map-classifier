<?php

declare(strict_types=1);

namespace MapClassifier\Classification;

/**
 * Natural environment of a map
 */
enum Biome: string
{
    case Forest = 'forest';
    case Jungle = 'jungle';
    case Plain = 'plain';
    case Field = 'field';
    case Desert = 'desert';
    case Mountain = 'mountain';
    case Swamp = 'swamp';
    case Snow = 'snow';
    case Beach = 'beach';
    case Sea = 'sea';
    case LakeRiver = 'lake_river';
    case Cave = 'cave';
    case Underground = 'underground';
    case Volcanic = 'volcanic';
    case Wasteland = 'wasteland';
    case Cemetery = 'cemetery';
    case Other = 'other';

    /**
     * Hint given to the model to describe the value
     */
    public function description(): string
    {
        return match ($this) {
            self::Forest => 'temperate woods, dense trees',
            self::Jungle => 'tropical vegetation, palm trees, lianas',
            self::Plain => 'open grassland, meadows, few trees',
            self::Field => 'cultivated land: crops, wheat, farmland',
            self::Desert => 'sand, dunes, arid land',
            self::Mountain => 'rocky terrain, cliffs, rocky paths',
            self::Swamp => 'marsh, bog, muddy water with vegetation',
            self::Snow => 'snow or ice covered land',
            self::Beach => 'sandy shore next to the sea',
            self::Sea => 'mostly open water, islands, boats',
            self::LakeRiver => 'land mostly structured around a lake or river',
            self::Cave => 'natural cave, rock walls in darkness',
            self::Underground => 'man-made underground: mine, sewer, crypt, cellar',
            self::Volcanic => 'lava, volcanic rocks, ashes',
            self::Wasteland => 'dead, corrupted or desolate land: dead trees, cracked ground',
            self::Cemetery => 'graveyard, tombs',
            self::Other => 'natural environment not listed above (fill biome_other)',
        };
    }
}
