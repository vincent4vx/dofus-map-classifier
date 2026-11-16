<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\MapSelection;
use MapClassifier\Map\Repository\MapFileRepository;

use function array_slice;
use function is_file;

/**
 * Select rendered maps which should be classified
 */
final readonly class MapsToClassify
{
    public function __construct(
        private MapFileRepository $maps,
        private MapResultRepository $results,
        private string $rendersDir,
    ) {}

    /**
     * @param bool $force Also select already classified maps
     * @param bool $retryErrors Also select maps with a classification error
     *
     * @return list<MapFile>
     */
    public function select(MapSelection $selection, bool $force = false, bool $retryErrors = false, ?int $limit = null): array
    {
        $todo = [];

        foreach ($selection->filter($this->maps->all()) as $map) {
            if (!is_file($this->rendersDir . '/' . $map->id . '.png')) {
                continue;
            }

            $result = $this->results->get($map->id);

            if ($result !== null && !$force && ($result->ok || !$retryErrors)) {
                continue;
            }

            $todo[] = $map;
        }

        return $limit !== null ? array_slice($todo, 0, $limit) : $todo;
    }
}
