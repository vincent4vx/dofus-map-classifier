<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use Arakne\MapParser\Loader\MapLoader;
use Arakne\MapParser\Loader\MapStructure;
use Arakne\Swf\SwfFile;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\RenderError;
use MapClassifier\Map\Repository\MapKeyRepository;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\MapSprites;
use Throwable;

/**
 * Extract the sprites used by a map, with their usage (cells per layer, slopes, movements…)
 */
final readonly class MapSpriteExtractor
{
    public function __construct(
        private MapKeyRepository $keys,
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

        foreach ($loaded->cells as $cell) {
            if ($cell->ground->number > 0) {
                // Flat ground has the slope 1 (0 is not valid)
                $sprites->addCell(Layer::Ground, $cell->ground->number, $cell->ground->slope > 1, $cell->movement);
            }

            if ($cell->layer1->number > 0) {
                $sprites->addCell(Layer::Layer1, $cell->layer1->number, movement: $cell->movement);
            }

            if ($cell->layer2->number > 0) {
                $sprites->addCell(Layer::Layer2, $cell->layer2->number, movement: $cell->movement);
            }
        }

        return $sprites;
    }
}
