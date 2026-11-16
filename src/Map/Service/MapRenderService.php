<?php

declare(strict_types=1);

namespace MapClassifier\Map\Service;

use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Repository\RenderErrorRepository;

use function array_slice;
use function is_file;

/**
 * Render maps to PNG files, and keep track of the maps which cannot be rendered
 */
final readonly class MapRenderService
{
    public function __construct(
        private MapImageRenderer $renderer,
        private RenderErrorRepository $errors,
        private string $rendersDir,
    ) {}

    /**
     * Select the maps to render
     *
     * @param array<int, MapFile> $maps
     * @param bool $force Render again already rendered maps, and maps in render error
     * @param bool $retryErrors Render again maps in render error
     *
     * @return list<MapFile>
     */
    public function select(array $maps, bool $force = false, bool $retryErrors = false, ?int $limit = null): array
    {
        $todo = [];

        foreach ($maps as $map) {
            if (!$force && is_file($this->target($map->id))) {
                continue;
            }

            if (!$force && !$retryErrors && $this->errors->get($map->id) !== null) {
                continue;
            }

            $todo[] = $map;
        }

        return $limit !== null ? array_slice($todo, 0, $limit) : $todo;
    }

    /**
     * Render the map, and store the failure if it cannot be rendered
     *
     * @return RenderException|null The error, or null on success
     */
    public function render(MapFile $map): ?RenderException
    {
        try {
            $this->renderer->renderTo($map, $this->target($map->id));
        } catch (RenderException $e) {
            $this->errors->save($map, $e);

            return $e;
        }

        // Previous render error is now obsolete
        $this->errors->delete($map->id);

        return null;
    }

    /**
     * Path of the rendered image of a map
     */
    public function target(int $id): string
    {
        return $this->rendersDir . '/' . $id . '.png';
    }
}
