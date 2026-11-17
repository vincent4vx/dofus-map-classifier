<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use Arakne\MapParser\Loader\MapLoader;
use Arakne\MapParser\Loader\MapStructure;
use Arakne\MapParser\Parser\Cell;
use Arakne\MapParser\Parser\LayerObjectInterface;
use Arakne\Swf\SwfFile;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\CellGrid;
use MapClassifier\Map\Model\CellOffset;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\RenderError;
use MapClassifier\Map\Repository\MapKeyRepository;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\MapSprites;
use MapClassifier\Sprite\Model\Orientation;
use MapClassifier\Sprite\Model\OrientedSprite;
use Throwable;

/**
 * Extract the sprites used by a map, with their usage (cells per layer, slopes, movements, neighbors…)
 */
final readonly class MapSpriteExtractor
{
    public function __construct(
        private MapKeyRepository $keys,
        /**
         * Neighbors are searched up to this number of cells on each axis of the grid
         * Large sprites (walls…) cover several cells: their parts are repeated every few cells
         */
        private int $neighborsRadius = 4,
        private MapLoader $loader = new MapLoader(),
    ) {}

    /**
     *
     * @throws RenderException When the map cannot be loaded
     */
    public function extract(MapFile $map): MapSprites
    {
        $attachments = [];

        if ($map->encrypted) {
            $attachments[] = $this->keys->find($map)
                ?? throw new RenderException(RenderError::MissingKey, 'No key found for map ' . $map->id . ' version ' . $map->version);
        }

        try {
            $loaded = $this->loader->load(MapStructure::fromSwfFile(new SwfFile($map->path)), ...$attachments);
        } catch (Throwable $e) {
            throw new RenderException(RenderError::RenderFailed, $e->getMessage(), $e);
        }

        $sprites = new MapSprites();

        if ($loaded->background > 0) {
            $sprites->addCell(Layer::Background, $loaded->background);
        }

        $grid = CellGrid::of($loaded);

        foreach ($loaded->cells as $cellId => $cell) {
            foreach ([Layer::Ground, Layer::Layer1, Layer::Layer2] as $layer) {
                $object = self::object($cell, $layer);

                if ($object->number > 0) {
                    // Flat ground has the slope 1 (0 is not valid)
                    $sprites->addCell($layer, $object->number, $layer === Layer::Ground && $cell->ground->slope > 1, $cell->movement);
                    $sprites->addNeighbors($layer, $object->number, Orientation::of($object), $this->neighbors($loaded->cells, $grid, $cellId, $layer));
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

                $object = self::object($cells[$neighborId], $layer);

                if ($object->number > 0) {
                    $neighbors[new CellOffset($dx, $dy)->key()] = new OrientedSprite($object->number, Orientation::of($object));
                }
            }
        }

        return $neighbors;
    }

    /**
     * Sprite of the cell on the given layer (the background is not drawn on cells: the ground is returned)
     */
    private static function object(Cell $cell, Layer $layer): LayerObjectInterface
    {
        return match ($layer) {
            Layer::Layer1 => $cell->layer1,
            Layer::Layer2 => $cell->layer2,
            default => $cell->ground,
        };
    }
}
