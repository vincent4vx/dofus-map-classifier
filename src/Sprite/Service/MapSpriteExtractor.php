<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use Arakne\MapParser\Parser\Cell;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\CellGrid;
use MapClassifier\Map\Model\CellOffset;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\PreferredPath;
use MapClassifier\Map\Service\MapImageRenderer;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\MapSprites;
use MapClassifier\Sprite\Model\Orientation;
use MapClassifier\Sprite\Model\OrientedSprite;

/**
 * Extract the sprites used by a map, with their usage (cells per layer, slopes, movements, distance to the preferred path, neighbors…)
 */
final readonly class MapSpriteExtractor
{
    public function __construct(
        /** Used to load the maps */
        private MapImageRenderer $maps,
        /**
         * Neighbors are searched up to this number of cells on each axis of the grid
         * Large sprites (walls…) cover several cells: their parts are repeated every few cells
         */
        private int $neighborsRadius = 4,
    ) {}

    /**
     * @throws RenderException When the map cannot be loaded
     */
    public function extract(MapFile $map): MapSprites
    {
        $loaded = $this->maps->load($map);

        $sprites = new MapSprites();

        if ($loaded->background > 0) {
            $sprites->addCell(Layer::Background, $loaded->background);
        }

        $grid = CellGrid::of($loaded);
        $path = PreferredPath::of($loaded, $grid);

        foreach ($loaded->cells as $cellId => $cell) {
            if ($cell->active) {
                $sprites->addActiveCell($cell->ground->number <= 0);
            }

            foreach ([Layer::Ground, Layer::Layer1, Layer::Layer2] as $layer) {
                $object = $layer->object($cell);

                if ($object !== null && $object->number > 0) {
                    // Flat ground has the slope 1 (0 is not valid)
                    $sloped = $layer === Layer::Ground && $cell->ground->slope > 1;
                    $orientation = Orientation::of($object);
                    $sprites->addCell($layer, $object->number, $sloped, $cell->movement, $cellId, $grid->distanceToCenter($cellId), $orientation, $path->distance($cellId));
                    $sprites->addNeighbors($layer, $object->number, $orientation, $this->neighbors($loaded->cells, $grid, $cellId, $layer));
                }
            }
        }

        return $sprites;
    }

    /**
     * Sprites on the cells around, on the given layer, up to {@see $neighborsRadius} cells on each axis
     *
     * @param array<int, Cell> $cells
     * @return array<string, OrientedSprite> Indexed by {@see CellOffset::key()}
     */
    private function neighbors(array $cells, CellGrid $grid, int $cellId, Layer $layer): array
    {
        [$x, $y] = $grid->coordinates($cellId);
        $neighbors = [];

        for ($dx = -$this->neighborsRadius; $dx <= $this->neighborsRadius; ++$dx) {
            for ($dy = -$this->neighborsRadius; $dy <= $this->neighborsRadius; ++$dy) {
                if (($dx === 0 && $dy === 0) || ($neighborId = $grid->cellId($x + $dx, $y + $dy)) === null) {
                    continue;
                }

                $object = $layer->object($cells[$neighborId]);

                if ($object !== null && $object->number > 0) {
                    $neighbors[new CellOffset($dx, $dy)->key()] = new OrientedSprite($object->number, Orientation::of($object));
                }
            }
        }

        return $neighbors;
    }
}
