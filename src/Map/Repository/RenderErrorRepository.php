<?php

declare(strict_types=1);

namespace MapClassifier\Map\Repository;

use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\RenderError;

/**
 * Store the maps which cannot be rendered
 * Implemented by the classification domain, which stores them as map results
 */
interface RenderErrorRepository
{
    /**
     * Render error of the map, or null if it has no render error
     */
    public function get(int $id): ?RenderError;

    public function save(MapFile $map, RenderException $error): void;

    /**
     * Remove the render error of the map, if any
     */
    public function delete(int $id): void;
}
