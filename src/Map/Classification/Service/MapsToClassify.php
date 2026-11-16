<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Map\Classification\Batch\Repository\BatchRecordRepository;
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
        private BatchRecordRepository $batches,
        private string $rendersDir,
    ) {}

    /**
     * @param bool $force Also select already classified maps
     * @param bool $retryErrors Also select maps with a classification error
     * @param bool $includePending Also select maps which are part of a batch not yet imported
     *
     * @return list<MapFile>
     */
    public function select(MapSelection $selection, bool $force = false, bool $retryErrors = false, bool $includePending = false, ?int $limit = null): array
    {
        $pending = $includePending ? [] : $this->batches->pendingMapIds();
        $todo = [];

        foreach ($selection->filter($this->maps->all()) as $map) {
            if (isset($pending[$map->id]) || !is_file($this->rendersDir . '/' . $map->id . '.png')) {
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
