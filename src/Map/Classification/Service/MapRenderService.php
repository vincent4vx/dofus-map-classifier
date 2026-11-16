<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Service\MapImageRenderer;

use function array_slice;
use function array_values;
use function is_file;

/**
 * Render maps to the PNG files sent to the model
 * Render failures are stored as map results, so they are not classified
 */
final readonly class MapRenderService
{
    public function __construct(
        private MapImageRenderer $renderer,
        private MapResultRepository $results,
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

            if (!$force && !$retryErrors && ($this->results->get($map->id)?->error?->isRenderError() ?? false)) {
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
            $this->results->save(MapResult::failure($map->id, $map->version, ErrorCode::fromRenderError($e->error), $e->getMessage()));

            return $e;
        }

        // Previous render error is now obsolete
        if ($this->results->get($map->id)?->error?->isRenderError()) {
            $this->results->delete($map->id);
        }

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
