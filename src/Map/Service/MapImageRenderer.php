<?php

declare(strict_types=1);

namespace MapClassifier\Map\Service;

use Arakne\MapParser\DofusMapParser;
use Arakne\MapParser\Loader\Map;
use Arakne\MapParser\Loader\MapStructure;
use Arakne\MapParser\Parser\GroundObject;
use Arakne\MapParser\Parser\LayerObjectInterface;
use Arakne\MapParser\Renderer\CellShape;
use Arakne\MapParser\Renderer\MapScale;
use Arakne\MapParser\Sprite\Cache\SqliteSpriteCache;
use Arakne\Swf\SwfFile;
use GdImage;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\Rectangle;
use MapClassifier\Map\Model\RenderError;
use MapClassifier\Map\Repository\MapKeyRepository;
use RuntimeException;
use Throwable;

use function dirname;
use function imagepng;
use function is_dir;
use function mkdir;
use function rename;

/**
 * Render client maps to PNG files using php-map-parser
 */
final class MapImageRenderer
{
    private DofusMapParser $parser {
        get => $this->parser ??= new DofusMapParser(
            dofusPath: $this->dofusPath,
            spriteCache: new SqliteSpriteCache($this->cacheDir . '/sprites.db', inMemoryCapacity: 500),
        );
    }

    public function __construct(
        private readonly string $dofusPath,
        private readonly string $cacheDir,
        private readonly MapKeyRepository $keys,
    ) {}

    /**
     * @throws RenderException When the map key is missing, or the map cannot be loaded or rendered
     */
    public function renderTo(MapFile $map, string $target): void
    {
        $image = $this->render($this->load($map));

        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }

        // Write in a temporary file to never keep a partial image on crash
        $tmp = $target . '.tmp';

        if (!imagepng($image, $tmp) || !rename($tmp, $target)) {
            throw new RuntimeException('Cannot write image ' . $target);
        }
    }

    /**
     * Load the cells of the map, decrypted with its key
     *
     * @throws RenderException When the map key is missing, or the map cannot be loaded
     */
    public function load(MapFile $map): Map
    {
        $attachments = [];

        if ($map->encrypted) {
            $attachments[] = $this->keys->find($map)
                ?? throw new RenderException(RenderError::MissingKey, 'No key found for map ' . $map->id . ' version ' . $map->version);
        }

        try {
            return $this->parser->loader->load(MapStructure::fromSwfFile(new SwfFile($map->path)), ...$attachments);
        } catch (Throwable $e) {
            throw new RenderException(RenderError::RenderFailed, $e->getMessage(), $e);
        }
    }

    /**
     * @throws RenderException When the map cannot be rendered
     */
    public function render(Map $map): GdImage
    {
        try {
            return $this->parser->renderer->render($map);
        } catch (Throwable $e) {
            throw new RenderException(RenderError::RenderFailed, $e->getMessage(), $e);
        }
    }

    /**
     * Area of the rendered map image covered by a sprite drawn on a cell, computed like the renderer draws it
     * The area may exceed the image for a sprite on the border of the map
     *
     * @param LayerObjectInterface $object Ground or object of the cell
     *
     * @return Rectangle|null Null if the cell does not exist, or the sprite cannot be drawn
     */
    public function spriteBounds(Map $map, int $cellId, LayerObjectInterface $object): ?Rectangle
    {
        $box = $this->spriteBox($map, $cellId, $object);

        if ($box === null) {
            return null;
        }

        $scale = MapScale::for($map);
        $position = $scale->applyToCoordinates($box->x, $box->y);

        return new Rectangle($position->x, $position->y, (int) ($box->width * $scale->scale), (int) ($box->height * $scale->scale));
    }

    /**
     * Area covered by a sprite drawn on a cell, in game pixels (the coordinates of the cells, before the scaling of the image)
     *
     * @param LayerObjectInterface $object Ground or object of the cell
     *
     * @return Rectangle|null Null if the cell does not exist, or the sprite cannot be drawn
     */
    public function spriteBox(Map $map, int $cellId, LayerObjectInterface $object): ?Rectangle
    {
        $shape = $cellId >= 0 ? CellShape::fromCellId($map, $cellId) : null;
        $sprite = ($object instanceof GroundObject ? $this->parser->grounds : $this->parser->objects)->get($object->number);

        if ($shape === null || !$sprite->valid) {
            return null;
        }

        if ($object->rotation !== 0) {
            $sprite = $sprite->rotate($object->rotation);
        }

        if ($object->flip) {
            $sprite = $sprite->flip();
        }

        return new Rectangle((int) ($shape->x + $sprite->offsetX), (int) ($shape->y + $sprite->offsetY), (int) $sprite->width, (int) $sprite->height);
    }
}
