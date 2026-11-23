<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

use Arakne\MapParser\Loader\Map;

use function abs;
use function max;

/**
 * Cells of a map with a preferred movement (5 to 7, the highest values): the path the characters walk on, placed by hand by the map designers
 *
 * The preferred cells are a line of one cell wide, on the middle of the roads, streets and bridges: the sprites drawing a road are on it,
 * its borders on the cells next to it. The line is precise (the cells are almost always on the drawn road), but often incomplete
 * (only a part of the roads of the map is marked). Most maps have no preferred cell at all.
 */
final readonly class PreferredPath
{
    /** Minimal movement of a preferred cell */
    public const int MIN_MOVEMENT = 5;

    /** Distances to the path are computed up to this number of cells: farther cells are at {@see FAR} */
    public const int MAX_DISTANCE = 2;

    /** Distance of the cells farther than {@see MAX_DISTANCE} */
    public const int FAR = self::MAX_DISTANCE + 1;

    /** @var array<int, array<int, true>> Preferred cells, indexed by x, then y coordinates */
    private array $cells;

    /**
     * @param list<int> $cellIds Ids of the preferred cells
     */
    public function __construct(
        private CellGrid $grid,
        array $cellIds,
    ) {
        $cells = [];

        foreach ($cellIds as $cellId) {
            [$x, $y] = $grid->coordinates($cellId);
            $cells[$x][$y] = true;
        }

        $this->cells = $cells;
    }

    /**
     * Preferred cells of the map: active (usable) cells, with a movement from 5 to 7
     */
    public static function of(Map $map, CellGrid $grid): self
    {
        $cellIds = [];

        foreach ($map->cells as $cellId => $cell) {
            if ($cell->active && $cell->movement >= self::MIN_MOVEMENT) {
                $cellIds[] = $cellId;
            }
        }

        return new self($grid, $cellIds);
    }

    /**
     * The map has at least one preferred cell
     */
    public function exists(): bool
    {
        return $this->cells !== [];
    }

    /**
     * Distance between the cell and the nearest preferred cell, in cells, on the axes of the grid or in diagonal (Chebyshev distance)
     *
     * 0 for a preferred cell, 1 for a cell touching one (including by a corner)... up to {@see MAX_DISTANCE}, then {@see FAR}.
     * Null if the map has no preferred cell.
     */
    public function distance(int $cellId): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        [$x, $y] = $this->grid->coordinates($cellId);
        $nearest = self::FAR;

        for ($dx = -self::MAX_DISTANCE; $dx <= self::MAX_DISTANCE; ++$dx) {
            for ($dy = -self::MAX_DISTANCE; $dy <= self::MAX_DISTANCE; ++$dy) {
                $distance = max(abs($dx), abs($dy));

                if ($distance < $nearest && isset($this->cells[$x + $dx][$y + $dy])) {
                    $nearest = $distance;
                }
            }
        }

        return $nearest;
    }
}
