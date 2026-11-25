<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

use function array_intersect_key;
use function array_map;
use function array_sum;
use function count;

/**
 * Sprites used by a map, with their usage (see {@see \MapClassifier\Sprite\Service\MapSpriteExtractor})
 *
 * @implements IteratorAggregate<int, MapSpriteUsage>
 */
final class MapSprites implements IteratorAggregate, Countable
{
    /** Number of cells with a ground sprite */
    public private(set) int $groundCells = 0;

    /** Number of active (usable) cells */
    public private(set) int $activeCells = 0;

    /** Number of active cells without ground sprite: the background is visible through them */
    public private(set) int $bareCells = 0;

    /** The map has a preferred path (see {@see \MapClassifier\Map\Model\PreferredPath}) */
    public bool $hasPreferredPath = false;

    /** Number of cells of the map, inactive ones included: the denominator of the covered shares */
    public int $cells = 0;

    /** @var array<int, true> Active cells, when their id is known */
    private array $activeCellIds = [];

    /** @var array<string, array<int, MapSpriteUsage>> Indexed by set value, then sprite id */
    private array $usages = [];

    /**
     * Count a cell (or the background) using the sprite
     *
     * @param bool $sloped The cell is a ground cell with a slope
     * @param int|null $movement Movement of the cell, null for the background
     * @param int|null $cellId Id of the cell, null for the background
     * @param float $centerDistance Distance between the cell and the center of the map
     * @param Orientation $orientation Rotation and flip of the sprite on the cell
     * @param int|null $pathDistance Distance between the cell and the preferred path of the map, null if the map has no preferred path
     */
    public function addCell(Layer $layer, int $id, bool $sloped = false, ?int $movement = null, ?int $cellId = null, float $centerDistance = 0.0, Orientation $orientation = new Orientation(), ?int $pathDistance = null): void
    {
        if ($layer === Layer::Ground) {
            ++$this->groundCells;
        }

        $set = $layer->spriteSet();
        $this->usages[$set->value][$id] ??= new MapSpriteUsage($set, $id);
        $this->usages[$set->value][$id]->addCell($layer, $sloped, $movement, $cellId, $centerDistance, $orientation, $pathDistance);
    }

    /**
     * Count the neighbors around a cell using the sprite, on the same layer (see {@see MapSpriteUsage::addNeighbors()})
     *
     * @param array<string, OrientedSprite> $neighbors Indexed by {@see \MapClassifier\Map\Model\CellOffset::key()}
     */
    public function addNeighbors(Layer $layer, int $id, Orientation $orientation, array $neighbors): void
    {
        $set = $layer->spriteSet();
        $this->usages[$set->value][$id] ??= new MapSpriteUsage($set, $id);
        $this->usages[$set->value][$id]->addNeighbors($layer, $orientation, $neighbors);
    }

    /**
     * Count the cells whose centre lies under one use of an object sprite (see {@see MapSpriteUsage::addCover()})
     *
     * @param list<int> $cellIds
     */
    public function addCover(Layer $layer, int $id, array $cellIds): void
    {
        $set = $layer->spriteSet();
        $this->usages[$set->value][$id] ??= new MapSpriteUsage($set, $id);
        $this->usages[$set->value][$id]->addCover($cellIds);
    }

    /**
     * Count a cell using an object on a map with water (see {@see MapSpriteUsage::addShore()})
     *
     * @param list<\MapClassifier\Map\Model\Direction> $land
     * @param list<\MapClassifier\Map\Model\Direction> $water
     */
    public function addShore(Layer $layer, int $id, ?int $fill, array $land = [], array $water = []): void
    {
        $set = $layer->spriteSet();
        $this->usages[$set->value][$id] ??= new MapSpriteUsage($set, $id);
        $this->usages[$set->value][$id]->addShore($fill, $land, $water);
    }

    /**
     * Ratio (0 to 1) of the cells of the map lying under the uses of a sprite. Null if the map has no cell
     */
    public function coverShare(MapSpriteUsage $usage): ?float
    {
        return $this->cells > 0 ? count($usage->coveredCells) / $this->cells : null;
    }

    /**
     * Count an active cell of the map
     *
     * @param bool $bare The cell has no ground sprite
     * @param int|null $cellId Id of the cell, to compute {@see floorShare()}
     */
    public function addActiveCell(bool $bare, ?int $cellId = null): void
    {
        ++$this->activeCells;

        if ($cellId !== null) {
            $this->activeCellIds[$cellId] = true;
        }

        if ($bare) {
            ++$this->bareCells;
        }
    }

    /**
     * Ratio (0 to 1) of the active cells lying under any of the given object sprites (their union, see
     * {@see MapSpriteUsage::addCover()}). Null if the active cells are unknown
     *
     * @param array<int, true> $objects Ids of object sprites, as keys
     */
    public function floorShare(array $objects): ?float
    {
        if ($this->activeCellIds === []) {
            return null;
        }

        $covered = [];

        foreach ($this->usages[SpriteSet::Objects->value] ?? [] as $id => $usage) {
            if (isset($objects[$id])) {
                $covered += array_intersect_key($usage->coveredCells, $this->activeCellIds);
            }
        }

        return count($covered) / count($this->activeCellIds);
    }

    /**
     * Ratio (0 to 1) of the active cells without ground sprite. Null if the map has no active cell
     */
    public function bareShare(): ?float
    {
        return $this->activeCells > 0 ? $this->bareCells / $this->activeCells : null;
    }

    public function get(SpriteSet $set, int $id): ?MapSpriteUsage
    {
        return $this->usages[$set->value][$id] ?? null;
    }

    /**
     * Number of different sprites
     */
    #[Override]
    public function count(): int
    {
        return array_sum(array_map(count(...), $this->usages));
    }

    /**
     * One usage per sprite, in the order of first use
     */
    #[Override]
    public function getIterator(): Traversable
    {
        foreach ($this->usages as $usages) {
            yield from $usages;
        }
    }
}
