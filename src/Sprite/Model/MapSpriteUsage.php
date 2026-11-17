<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

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

    public function __construct(
        public readonly SpriteSet $set,
        public readonly int $id,
    ) {}

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
     */
    public function addCell(Layer $layer, bool $sloped = false, ?int $movement = null): void
    {
        $this->cells[$layer->value] = ($this->cells[$layer->value] ?? 0) + 1;

        if ($sloped) {
            ++$this->slopedCells;
        }

        if ($movement !== null) {
            $this->movements[$movement] = ($this->movements[$movement] ?? 0) + 1;
        }
    }
}
