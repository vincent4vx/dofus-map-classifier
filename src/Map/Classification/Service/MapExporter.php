<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\Export\ExportedLocation;
use MapClassifier\Map\Classification\Model\Export\ExportedMap;
use MapClassifier\Map\Classification\Model\MapExport;
use MapClassifier\Map\Classification\Repository\MapResultRepository;

use function arsort;

/**
 * Merge all map results with their location and sub area biome
 */
final readonly class MapExporter
{
    public function __construct(
        private MapResultRepository $results,
        private SubAreaBiomeResolver $subAreaBiomes = new SubAreaBiomeResolver(),
    ) {}

    /**
     * @param array<int, MapLocation> $locations All locations, indexed by map id. Empty if not available
     * @param bool $onlyOk Exclude maps in error
     */
    public function export(array $locations, bool $onlyOk = false): MapExport
    {
        $maps = [];
        $biomes = [];
        $errors = 0;
        $results = $this->results->all();
        $subAreas = $this->subAreaBiomes->resolve($locations, $results);

        foreach ($results as $id => $result) {
            if (!$result->ok) {
                ++$errors;

                if ($onlyOk) {
                    continue;
                }
            } else {
                $biome = $result->classification->biome->value ?? '(none)';
                $biomes[$biome] = ($biomes[$biome] ?? 0) + 1;
            }

            $location = $locations[$id] ?? null;
            $maps[$id] = new ExportedMap(
                $result,
                $location === null ? null : ExportedLocation::fromLocation($location, $subAreas[$location->subAreaId]->biome),
            );
        }

        arsort($biomes);

        return new MapExport($maps, $subAreas, $biomes, $errors);
    }
}
