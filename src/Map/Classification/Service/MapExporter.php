<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\BiomeFilter;
use MapClassifier\Map\Classification\Model\Export\ExportedLocation;
use MapClassifier\Map\Classification\Model\Export\ExportedMap;
use MapClassifier\Map\Classification\Model\MapExport;
use MapClassifier\Map\Classification\Repository\MapResultRepository;
use MapClassifier\Map\Model\MapSelection;

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
     * Sub areas biomes are always computed from all the maps
     *
     * @param array<int, MapLocation> $locations All locations, indexed by map id. Empty if not available
     * @param MapSelection|null $selection Only export these maps
     * @param BiomeFilter|null $biome Only export classified maps of this biome
     * @param bool $onlyOk Exclude maps in error
     */
    public function export(array $locations, ?MapSelection $selection = null, ?BiomeFilter $biome = null, bool $onlyOk = false): MapExport
    {
        $maps = [];
        $biomes = [];
        $errors = 0;
        $results = $this->results->all();
        $subAreas = $this->subAreaBiomes->resolve($locations, $results);

        foreach ($results as $id => $result) {
            if ($selection !== null && !$selection->matches($id)) {
                continue;
            }

            if ($biome !== null && !$biome->matches($result)) {
                continue;
            }

            if (!$result->ok) {
                ++$errors;

                if ($onlyOk) {
                    continue;
                }
            } else {
                $mapBiome = $result->classification->biome->value ?? '(none)';
                $biomes[$mapBiome] = ($biomes[$mapBiome] ?? 0) + 1;
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
