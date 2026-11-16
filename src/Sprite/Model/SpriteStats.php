<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\MapClassification;

use function array_sum;

/**
 * Usage of a sprite, accumulated over the maps using it (see {@see \MapClassifier\Sprite\Service\SpriteAnalyzer})
 *
 * Each map using the sprite is counted once, by {@see addMap()}.
 * To aggregate a new data: extract it in {@see MapSpriteUsage} (for usage data) or take it from the map, count it here in {@see addMap()},
 * and add it to {@see SpriteProfile}.
 */
final class SpriteStats
{
    /** @var array<string, int> Number of cells (or backgrounds) using the sprite, indexed by layer value */
    private array $cells = [];

    /** Number of maps using the sprite */
    public private(set) int $maps = 0;

    /** Number of classified maps using the sprite */
    public private(set) int $classifiedMaps = 0;

    /** @var array<int, UsageCount> Usage indexed by area id */
    public private(set) array $areas = [];

    /** @var array<int, UsageCount> Usage indexed by sub area id */
    public private(set) array $subAreas = [];

    /** @var array<string, UsageCount> Usage indexed by biome value */
    public private(set) array $biomes = [];

    /** @var array<string, UsageCount> Usage on classified maps, indexed by "indoor" or "outdoor" */
    public private(set) array $indoor = [];

    /** @var array<string, UsageCount> Usage on classified maps, indexed by location type value */
    public private(set) array $locationTypes = [];

    /** Number of ground cells with a slope (not flat) using the sprite */
    public private(set) int $slopedCells = 0;

    /**
     * Count a map using the sprite: its usage on the map, and the map attributes. Must be called once per map.
     *
     * @param MapSpriteUsage $usage Usage of the sprite on the map
     * @param Biome|null $biome Biome of the map, or of its sub area
     * @param MapClassification|null $classification Null if the map is not classified
     */
    public function addMap(MapSpriteUsage $usage, ?MapLocation $location, ?Biome $biome, ?MapClassification $classification): void
    {
        ++$this->maps;

        foreach ($usage->cells as $layer => $count) {
            $this->cells[$layer] = ($this->cells[$layer] ?? 0) + $count;
        }

        $occurrences = $usage->occurrences();

        $this->slopedCells += $usage->slopedCells;

        if ($location !== null) {
            self::increment($this->subAreas, $location->subAreaId, $occurrences);

            if ($location->areaId !== null) {
                self::increment($this->areas, $location->areaId, $occurrences);
            }
        }

        if ($biome !== null) {
            self::increment($this->biomes, $biome->value, $occurrences);
        }

        if ($classification !== null) {
            ++$this->classifiedMaps;
            self::increment($this->indoor, $classification->indoor ? 'indoor' : 'outdoor', $occurrences);
            self::increment($this->locationTypes, $classification->locationType->value, $occurrences);
        }
    }

    /**
     * Layer using the sprite on the most cells
     * On equality, the last layer in the {@see Layer} order is used (e.g. layer2 rather than layer1), so the result does not depend on the reading order
     */
    public function mainLayer(): Layer
    {
        $main = null;

        foreach (Layer::cases() as $layer) {
            if (($this->cells[$layer->value] ?? 0) >= ($main === null ? 1 : $this->cells[$main->value])) {
                $main = $layer;
            }
        }

        return $main ?? Layer::Background;
    }

    /**
     * Total number of cells (or backgrounds) using the sprite
     */
    public function occurrences(): int
    {
        return array_sum($this->cells);
    }

    /**
     * @param array<array-key, UsageCount> $counts
     * @param int $occurrences Number of uses of the sprite on the map
     */
    private static function increment(array &$counts, int|string $key, int $occurrences): void
    {
        $counts[$key] = ($counts[$key] ?? new UsageCount())->withMap($occurrences);
    }
}
