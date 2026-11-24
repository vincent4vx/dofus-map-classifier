<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\MapClassification;
use MapClassifier\Map\Model\PreferredPath;

use function array_sum;
use function array_map;
use function array_values;
use function count;
use function ksort;
use function round;
use function uasort;

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

    /** @var array<int, int> Number of cells using the sprite, indexed by the movement of the cell */
    public private(set) array $movements = [];

    /** @var array<int, int> Number of cells on maps with a preferred path, indexed by distance to the path (see {@see MapSpriteUsage::$pathDistances}) */
    private array $pathDistances = [];

    /** @var array<string, array<string, NeighborCounts>> Neighbors of the cells using the sprite, indexed by layer value, then {@see Orientation::key()} */
    private array $neighbors = [];

    /** @var array<string, array{map: int, cell: int, orientation: Orientation, cells: int, distance: float}> Best example of each layer (see {@see example()}), indexed by layer value */
    private array $examples = [];

    /** Sum, over the maps using the sprite as ground, of the ratio of the ground cells of the map using the sprite */
    private float $groundShares = 0.0;

    /** Number of maps using the sprite as ground, with ground cells */
    private int $groundMaps = 0;

    /** Sum, over the maps using the sprite as background, of the ratio of their active cells without ground */
    private float $bareShares = 0.0;

    /** Number of maps using the sprite as background, with active cells */
    private int $bareMaps = 0;

    /** Sum, over the maps using the sprite as an object, of the ratio of their cells lying under it */
    private float $coverShares = 0.0;

    /** Number of maps using the sprite as an object, covering at least one cell */
    private int $coverMaps = 0;

    public function __construct(
        /**
         * Also accumulate the neighbors and the example of use. False for a light breakdown (by location type in an area),
         * whose neighbors would only repeat the ones of the area at a high memory cost
         */
        private readonly bool $detailed = true,
    ) {}

    /**
     * Count a map using the sprite: its usage on the map, and the map attributes. Must be called once per map.
     *
     * @param MapSpriteUsage $usage Usage of the sprite on the map
     * @param Biome|null $biome Biome of the map, or of its sub area
     * @param MapClassification|null $classification Null if the map is not classified
     * @param int|null $mapId Id of the map, to choose an example of use. Null to not use the map as example
     * @param int $mapGroundCells Number of cells of the map with a ground sprite, to compute {@see groundShare()}
     * @param float|null $mapBareShare Ratio of the active cells of the map without ground, to compute {@see bareShare()}
     * @param int $mapCells Number of cells of the map, to compute {@see coverShare()}
     */
    public function addMap(MapSpriteUsage $usage, ?MapLocation $location, ?Biome $biome, ?MapClassification $classification, ?int $mapId = null, int $mapGroundCells = 0, ?float $mapBareShare = null, int $mapCells = 0): void
    {
        if ($usage->coveredCells !== [] && $mapCells > 0) {
            $this->coverShares += count($usage->coveredCells) / $mapCells;
            ++$this->coverMaps;
        }

        if (($usage->cells[Layer::Background->value] ?? 0) > 0 && $mapBareShare !== null) {
            $this->bareShares += $mapBareShare;
            ++$this->bareMaps;
        }

        if (($groundCells = $usage->cells[Layer::Ground->value] ?? 0) > 0 && $mapGroundCells > 0) {
            $this->groundShares += $groundCells / $mapGroundCells;
            ++$this->groundMaps;
        }

        ++$this->maps;

        foreach ($usage->cells as $layer => $count) {
            $this->cells[$layer] = ($this->cells[$layer] ?? 0) + $count;
        }

        $occurrences = $usage->occurrences();

        $this->slopedCells += $usage->slopedCells;

        foreach ($usage->movements as $movement => $count) {
            $this->movements[$movement] = ($this->movements[$movement] ?? 0) + $count;
        }

        foreach ($usage->pathDistances as $distance => $count) {
            $this->pathDistances[$distance] = ($this->pathDistances[$distance] ?? 0) + $count;
        }

        if ($this->detailed && $mapId !== null) {
            $this->addExample($usage, $mapId);
        }

        if ($this->detailed) {
            foreach ($usage->neighbors as $layer => $orientations) {
                foreach ($orientations as $key => $counts) {
                    ($this->neighbors[$layer][$key] ??= new NeighborCounts($counts->orientation))->merge($counts);
                }
            }
        }

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
     * Frequency of each movement on the cells using the sprite (0 to 1, sum of 1), sorted by movement
     * The background has no cell: a sprite only used as background has no movement
     *
     * @return array<int, float>
     */
    public function movementFrequencies(): array
    {
        $total = array_sum($this->movements);
        $frequencies = [];

        foreach ($this->movements as $movement => $count) {
            // 6 decimals: a single cell of a sprite used on a million cells is still visible
            $frequencies[$movement] = round($count / $total, 6);
        }

        ksort($frequencies);

        return $frequencies;
    }

    /**
     * Average ratio (0 to 1) of the active cells without ground, over the maps using the sprite as background, rounded to 4 decimals
     * A background visible on most of the map is the terrain itself, a hidden one only fills the holes. Null if the sprite is not used as background
     */
    public function bareShare(): ?float
    {
        return $this->bareMaps > 0 ? round($this->bareShares / $this->bareMaps, 4) : null;
    }

    /**
     * Average ratio (0 to 1) of the cells of its maps lying under the sprite (the centre of the cell inside the sprite), over the maps
     * using it as an object, rounded to 4 decimals. A large patch of grass repeated over the map covers most of it. Null for a ground
     * or a background, which covers its own cell or the whole map
     */
    public function coverShare(): ?float
    {
        return $this->coverMaps > 0 ? round($this->coverShares / $this->coverMaps, 4) : null;
    }

    /**
     * Position of the cells relative to the preferred path, on the maps with one. Null if the sprite is never used on such a map
     */
    public function pathProximity(): ?PathProximity
    {
        $total = array_sum($this->pathDistances);

        if ($total === 0) {
            return null;
        }

        $frequencies = [];

        for ($distance = 0; $distance <= PreferredPath::MAX_DISTANCE; ++$distance) {
            $frequencies[$distance] = round(($this->pathDistances[$distance] ?? 0) / $total, 4);
        }

        return new PathProximity($total, $frequencies);
    }

    /**
     * Frequent neighbors of the sprite on the given layer, for each orientation, sorted by number of cells desc
     *
     * @return list<OrientationNeighbors>
     */
    public function neighbors(Layer $layer): array
    {
        $orientations = $this->neighbors[$layer->value] ?? [];
        ksort($orientations);
        uasort($orientations, static fn (NeighborCounts $a, NeighborCounts $b) => $b->cells <=> $a->cells);

        return array_values(array_map(static fn (NeighborCounts $counts) => $counts->result(), $orientations));
    }

    /**
     * Use of the sprite to show as example, on the given layer: the map using the sprite on the most cells (so the sprite is shown
     * in its usual context, e.g. a wall with its other sections), and on this map, the cell the closest to the center (so the sprite is not cut)
     * On equality, the first map is kept
     */
    public function example(Layer $layer): ?SpriteExample
    {
        $example = $this->examples[$layer->value] ?? null;

        return $example !== null ? new SpriteExample($example['map'], $example['cell'], $example['orientation']) : null;
    }

    /**
     * Average ratio (0 to 1) of the ground cells of the maps covered by the sprite, over the maps using it as ground, rounded to 4 decimals
     * A base tile covers a large part of its maps, a variant tile only a few cells. Null if the sprite is not used as ground
     */
    public function groundShare(): ?float
    {
        return $this->groundMaps > 0 ? round($this->groundShares / $this->groundMaps, 4) : null;
    }

    /**
     * Total number of cells (or backgrounds) using the sprite
     */
    public function occurrences(): int
    {
        return array_sum($this->cells);
    }

    private function addExample(MapSpriteUsage $usage, int $mapId): void
    {
        foreach ($usage->centralCells as $layer => [$cellId, $distance, $orientation]) {
            $cells = $usage->cells[$layer] ?? 0;
            $best = $this->examples[$layer] ?? null;

            if ($best === null || $cells > $best['cells'] || ($cells === $best['cells'] && $distance < $best['distance'])) {
                $this->examples[$layer] = ['map' => $mapId, 'cell' => $cellId, 'orientation' => $orientation, 'cells' => $cells, 'distance' => $distance];
            }
        }
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
