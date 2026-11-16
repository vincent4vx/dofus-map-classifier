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
use Throwable;

/**
 * Extract the sprites used by a map, per layer
 */
final readonly class MapSpriteExtractor
{
    public function __construct(
        private MapKeyRepository $keys,
        private MapLoader $loader = new MapLoader(),
    ) {}

    /**
     * @return array<string, array<int, int>> Number of occurrences of each sprite number, indexed by layer value
     *
     * @throws RenderException When the map cannot be loaded
     */
    public function extract(MapFile $map): array
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

        $sprites = [];

        if ($loaded->background > 0) {
            $sprites[Layer::Background->value][$loaded->background] = 1;
        }

        foreach ($loaded->cells as $cell) {
            foreach ([
                Layer::Ground->value => $cell->ground->number,
                Layer::Layer1->value => $cell->layer1->number,
                Layer::Layer2->value => $cell->layer2->number,
            ] as $layer => $number) {
                if ($number > 0) {
                    $sprites[$layer][$number] = ($sprites[$layer][$number] ?? 0) + 1;
                }
            }
        }

        return $sprites;
    }
}
