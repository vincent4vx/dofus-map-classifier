<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\MapClassification;

use function array_key_first;
use function array_sum;
use function arsort;

/**
 * Usage of a sprite, accumulated over the maps using it (see {@see \MapClassifier\Sprite\Service\SpriteAnalyzer})
 *
 * Map attributes are counted once per map, by {@see addMap()}.
 * To aggregate a new attribute: add its counts here, fill them in {@see addMap()}, and add it to {@see SpriteProfile}.
 */
final class SpriteStats
{
    /** @var array<string, int> Number of cells (or backgrounds) using the sprite, indexed by layer value */
    private array $cells = [];

    /** Number of maps using the sprite */
    public private(set) int $maps = 0;

    /** Number of classified maps using the sprite */
    public private(set) int $classifiedMaps = 0;

    /** @var array<int, int> Number of maps, indexed by area id */
    public private(set) array $areas = [];

    /** @var array<int, int> Number of maps, indexed by sub area id */
    public private(set) array $subAreas = [];

    /** @var array<string, int> Number of maps, indexed by biome value */
    public private(set) array $biomes = [];

    /** @var array<string, int> Number of classified maps, indexed by "indoor" or "outdoor" */
    public private(set) array $indoor = [];

    /** @var array<string, int> Number of classified maps, indexed by location type value */
    public private(set) array $locationTypes = [];

    /**
     * Count the cells of a map layer using the sprite
     */
    public function addCells(Layer $layer, int $count): void
    {
        $this->cells[$layer->value] = ($this->cells[$layer->value] ?? 0) + $count;
    }

    /**
     * Count the attributes of a map using the sprite. Must be called once per map.
     *
     * @param Biome|null $biome Biome of the map, or of its sub area
     * @param MapClassification|null $classification Null if the map is not classified
     */
    public function addMap(?MapLocation $location, ?Biome $biome, ?MapClassification $classification): void
    {
        ++$this->maps;

        if ($location !== null) {
            self::increment($this->subAreas, $location->subAreaId);

            if ($location->areaId !== null) {
                self::increment($this->areas, $location->areaId);
            }
        }

        if ($biome !== null) {
            self::increment($this->biomes, $biome->value);
        }

        if ($classification !== null) {
            ++$this->classifiedMaps;
            self::increment($this->indoor, $classification->indoor ? 'indoor' : 'outdoor');
            self::increment($this->locationTypes, $classification->locationType->value);
        }
    }

    /**
     * Layer using the sprite on the most cells (the first counted one on equality)
     */
    public function mainLayer(): Layer
    {
        $cells = $this->cells;
        arsort($cells);

        return Layer::from((string) array_key_first($cells));
    }

    /**
     * Total number of cells (or backgrounds) using the sprite
     */
    public function occurrences(): int
    {
        return array_sum($this->cells);
    }

    /**
     * @param array<array-key, int> $counts
     */
    private static function increment(array &$counts, int|string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
}
