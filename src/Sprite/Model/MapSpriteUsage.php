<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

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

    public function __construct(
        public readonly SpriteSet $set,
        public readonly int $id,
    ) {}

    /**
     * Count a cell (or the background) using the sprite
     *
     * @param bool $sloped The cell is a ground cell with a slope
     */
    public function addCell(Layer $layer, bool $sloped = false): void
    {
        $this->cells[$layer->value] = ($this->cells[$layer->value] ?? 0) + 1;

        if ($sloped) {
            ++$this->slopedCells;
        }
    }
}
