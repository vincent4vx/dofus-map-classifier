<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use MapClassifier\Map\Model\Direction;

use function array_sum;

/**
 * Usage of a sprite on a map (see {@see \MapClassifier\Sprite\Service\MapSpriteExtractor})
 *
 * To extract a new data on the sprites: add it here, fill it in the extractor, and aggregate it in {@see SpriteStats::addMap()}
 */
final class MapSpriteUsage
{
    /** @var array<string, int> Number of cells (or 1 for the background) using the sprite, indexed by layer value */
    public private(set) array $cells = [];

    /** Number of ground cells with a slope (slope other than 0 and 1, i.e. not flat) using the sprite */
    public private(set) int $slopedCells = 0;

    /** @var array<int, int> Number of cells using the sprite, indexed by the movement of the cell (the background has no cell) */
    public private(set) array $movements = [];

    /**
     * @var array<int, int> Number of cells using the sprite, indexed by their distance to the preferred path of the map (0 to 2, 3 for the farther cells,
     *     see {@see \MapClassifier\Map\Model\PreferredPath::distance()}). Empty if the map has no preferred path
     */
    public private(set) array $pathDistances = [];

    /** @var array<string, array<string, NeighborCounts>> Neighbors of the cells using the sprite, indexed by layer value, then {@see Orientation::key()} */
    public private(set) array $neighbors = [];

    /**
     * @var array<string, array{int, float, Orientation}> Cell using the sprite the closest to the center of the map, with its distance and the orientation
     *     of the sprite, indexed by layer value. A cell with the sprite drawn as default (not rotated, not flipped) is preferred, so the sprite is shown
     *     on the map as on its own image
     */
    public private(set) array $centralCells = [];

    /** Number of cells using the object on a map with water (see {@see addShore()}) */
    public private(set) int $shoreCells = 0;

    /** @var array<int, int> Number of water cells using the object, indexed by the water sprite of the cell */
    public private(set) array $waterFills = [];

    /** @var array<string, int> Number of water cells using the object, indexed by the land around them ({@see Direction::key()}) */
    public private(set) array $shoreLand = [];

    /** @var array<int, true> Cells of the map whose centre lies under the sprite, on any of its uses (see {@see addCover()}) */
    public private(set) array $coveredCells = [];

    public function __construct(
        public readonly SpriteSet $set,
        public readonly int $id,
    ) {}

    /**
     * Count the cells whose centre lies under one use of the sprite
     *
     * @param list<int> $cellIds
     */
    public function addCover(array $cellIds): void
    {
        foreach ($cellIds as $cellId) {
            $this->coveredCells[$cellId] = true;
        }
    }

    /**
     * Count a cell using the object, on a map with water
     *
     * @param int|null $fill Water sprite of the cell, null for a land cell
     * @param list<Direction> $land Directions of the land cells around a water cell
     */
    public function addShore(?int $fill, array $land = []): void
    {
        ++$this->shoreCells;

        if ($fill !== null) {
            $this->waterFills[$fill] = ($this->waterFills[$fill] ?? 0) + 1;
            $key = Direction::key($land);
            $this->shoreLand[$key] = ($this->shoreLand[$key] ?? 0) + 1;
        }
    }

    /**
     * Total number of cells (or backgrounds) using the sprite on the map
     */
    public function occurrences(): int
    {
        return array_sum($this->cells);
    }

    /**
     * Count a cell (or the background) using the sprite
     *
     * @param bool $sloped The cell is a ground cell with a slope
     * @param int|null $movement Movement of the cell (0: not walkable, 1: interactive object, 2 to 7: walkable), null for the background
     * @param int|null $cellId Id of the cell, null for the background
     * @param float $centerDistance Distance between the cell and the center of the map
     * @param Orientation $orientation Rotation and flip of the sprite on the cell
     * @param int|null $pathDistance Distance between the cell and the preferred path of the map, null if the map has no preferred path
     */
    public function addCell(Layer $layer, bool $sloped = false, ?int $movement = null, ?int $cellId = null, float $centerDistance = 0.0, Orientation $orientation = new Orientation(), ?int $pathDistance = null): void
    {
        $this->cells[$layer->value] = ($this->cells[$layer->value] ?? 0) + 1;

        if ($cellId !== null) {
            $current = $this->centralCells[$layer->value] ?? null;
            $default = $orientation->rotation === 0 && !$orientation->flip;
            $currentDefault = $current !== null && $current[2]->rotation === 0 && !$current[2]->flip;

            if ($current === null || ($default && !$currentDefault) || ($default === $currentDefault && $centerDistance < $current[1])) {
                $this->centralCells[$layer->value] = [$cellId, $centerDistance, $orientation];
            }
        }

        if ($sloped) {
            ++$this->slopedCells;
        }

        if ($movement !== null) {
            $this->movements[$movement] = ($this->movements[$movement] ?? 0) + 1;
        }

        if ($pathDistance !== null) {
            $this->pathDistances[$pathDistance] = ($this->pathDistances[$pathDistance] ?? 0) + 1;
        }
    }

    /**
     * Count the neighbors around a cell using the sprite, on the same layer
     * The cell itself must also be counted with {@see addCell()}
     *
     * @param Orientation $orientation Orientation of the sprite on the cell
     * @param array<string, OrientedSprite> $neighbors Sprites on the cells around, on the same layer, indexed by {@see \MapClassifier\Map\Model\CellOffset::key()}
     */
    public function addNeighbors(Layer $layer, Orientation $orientation, array $neighbors): void
    {
        $this->neighbors[$layer->value][$orientation->key()] ??= new NeighborCounts($orientation);
        $this->neighbors[$layer->value][$orientation->key()]->addCell($neighbors);
    }
}
