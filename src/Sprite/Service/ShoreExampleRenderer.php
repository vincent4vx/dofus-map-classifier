<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use GdImage;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\CellGrid;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\Rectangle;
use MapClassifier\Map\Model\WaterCells;
use MapClassifier\Map\Service\MapImageRenderer;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;
use RuntimeException;

use function array_map;
use function count;
use function imagecopy;
use function imagecreatetruecolor;
use function imagesx;
use function imagesy;
use function max;
use function min;

/**
 * Render an example of each shore piece along the water: a native map where it borders the water, cropped around it, showing only the
 * ground and the shore pieces, the piece framed in magenta
 *
 * The usual example of a sprite (see {@see SpriteExampleRenderer}) is the map using it the most, which may show a cliff along a plateau,
 * far from any water, under trees and houses. Here the trees, buildings and objects are removed, so that only the shore remains.
 */
final readonly class ShoreExampleRenderer
{
    /** A shore piece lies on water at least this often, on the maps having some: the cliffs also border plateaus (o377: 37 %) */
    public const float MIN_WATER_SHARE = 0.25;

    /** A floating plant (lily pads) lies on open water at least this often */
    public const float MAX_OPEN_WATER = 0.5;

    /** Space kept around the piece, in cells: its neighbours along the shore */
    private const int MARGIN_CELLS = 3;

    public function __construct(
        private MapImageRenderer $maps,
    ) {}

    /**
     * The shore pieces: the layer 1 objects lying on water along the land (see {@see \MapClassifier\Sprite\Model\ShoreContext})
     *
     * @return array<int, SpriteProfile> Indexed by sprite id
     */
    public static function shorePieces(SpriteProfiles $profiles): array
    {
        $pieces = [];

        foreach ($profiles->bySet(SpriteSet::Objects) as $profile) {
            if ($profile->layer === Layer::Layer1 && $profile->shore !== null && $profile->shore->waterShare >= self::MIN_WATER_SHARE
                && ($profile->shore->land[''] ?? 0.0) < self::MAX_OPEN_WATER) {
                $pieces[$profile->id] = $profile;
            }
        }

        return $pieces;
    }

    /**
     * Find, for each piece, the map where it borders the water the most (upright), and the cell of its use the closest to the center
     *
     * @param iterable<MapFile> $maps
     * @param array<int, true> $waterGrounds Ground sprites (and backgrounds) drawing water
     * @param array<int, true> $pieces Pieces to find, among all the shore pieces
     * @param callable(MapFile): void|null $onMap Called after each map
     *
     * @return array<int, array{MapFile, int}> The map and the cell, by piece id
     */
    public function find(iterable $maps, array $waterGrounds, array $pieces, ?callable $onMap = null): array
    {
        $best = [];

        foreach ($maps as $file) {
            try {
                $map = $this->maps->load($file);
            } catch (RenderException) {
                continue;
            }

            $grid = CellGrid::of($map);
            $water = WaterCells::of($map, $grid, $waterGrounds);
            $cells = [];

            foreach ($water->exists() ? $map->cells : [] as $cellId => $cell) {
                $object = $cell->layer1;

                if (isset($pieces[$object->number]) && $object->rotation === 0 && !$object->flip && $water->fill($cellId) !== null && $water->landAround($cellId) !== []) {
                    $cells[$object->number][] = $cellId;
                }
            }

            foreach ($cells as $piece => $ids) {
                if (count($ids) > ($best[$piece][2] ?? 0)) {
                    $central = $ids[0];

                    foreach ($ids as $id) {
                        if ($grid->distanceToCenter($id) < $grid->distanceToCenter($central)) {
                            $central = $id;
                        }
                    }

                    $best[$piece] = [$file, $central, count($ids)];
                }
            }

            if ($onMap) {
                $onMap($file);
            }
        }

        return array_map(static fn (array $found) => [$found[0], $found[1]], $best);
    }

    /**
     * Render the example of a piece: the ground and the shore pieces around it, the piece framed in magenta
     *
     * @param array<int, true> $shorePieces All the shore pieces, drawn around the piece
     *
     * @throws RenderException When the map cannot be rendered
     */
    public function render(MapFile $file, int $cellId, array $shorePieces): GdImage
    {
        $map = $this->maps->load($file);
        $object = $map->cells[$cellId]->layer1 ?? throw new RuntimeException('Cell ' . $cellId . ' not found on the map ' . $file->id);
        $bounds = $this->maps->spriteBounds($map, $cellId, $object) ?? throw new RuntimeException('Sprite not drawn on the map ' . $file->id);
        $image = $this->maps->renderGroundLayers($map, static fn (int $id) => isset($shorePieces[$id]));

        // The piece and its neighbours along the shore, a few cells around
        $scale = imagesx($image) / 742;
        $marginX = (int) (self::MARGIN_CELLS * 53 * $scale);
        $marginY = (int) (self::MARGIN_CELLS * 27 * $scale);
        $left = max(0, $bounds->x - $marginX);
        $top = max(0, $bounds->y - $marginY);
        $right = min(imagesx($image), $bounds->x + $bounds->width + $marginX);
        $bottom = min(imagesy($image), $bounds->y + $bounds->height + $marginY);
        $crop = imagecreatetruecolor(max(1, $right - $left), max(1, $bottom - $top)) ?: throw new RuntimeException('Cannot create image');
        imagecopy($crop, $image, 0, 0, $left, $top, $right - $left, $bottom - $top);

        return SpriteExampleRenderer::framed($crop, new Rectangle($bounds->x - $left, $bounds->y - $top, $bounds->width, $bounds->height));
    }
}
