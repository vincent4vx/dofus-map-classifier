<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

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
            self::Forest => 'woods: many trees spread over the map (even small trees, even with grass between them)',
            self::Jungle => 'tropical vegetation, palm trees, lianas',
            self::Plain => 'open grassland, meadows: grass with only a few isolated trees (around 5 or less)',
            self::Field => 'cultivated land: crops, wheat, farmland',
            self::Desert => 'sand, dunes, arid land',
            self::Mountain => 'rocky relief inside the map: rocky cliffs, stone walls, rocky slopes, boulders, rock floor, mountain paths (requires actual rock)',
            self::Swamp => 'marsh or bog: stagnant water (murky, brown or green, even opaque) covering a significant part of the ground, pools between vegetation, mangroves',
            self::Snow => 'snow or ice covered land',
            self::Beach => 'sandy shore next to the sea',
            self::Sea => 'mostly open water, islands, boats',
            self::LakeRiver => 'land mostly structured around a lake or river',
            self::Cave => 'natural cave, rock walls in darkness',
            self::Underground => 'man-made underground: mine, sewer, crypt, cellar',
            self::Volcanic => 'lava, volcanic rocks, ashes',
            self::Wasteland => 'dead, dry, corrupted or desolate land: dead or leafless trees, dry yellow grass, cracked ground',
            self::Cemetery => 'graveyard: tombstones, graves, crosses, tombs (even small ones)',
            self::Other => 'natural environment not listed above (fill biome_other)',
        };
    }
}
