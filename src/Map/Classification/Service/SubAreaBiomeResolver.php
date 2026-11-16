<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Model\SubAreaBiome;

use function array_flip;
use function array_key_first;
use function array_map;
use function ksort;
use function uksort;

/**
 * Deduce the biome of each sub area from the biomes of its classified maps
 *
 * The biome is the most frequent one among outdoor maps, because interiors (houses, shops, caves…)
 * are less representative of the environment. If the sub area has no outdoor map with a biome, all maps are used.
 * Maps without biome are ignored.
 */
final readonly class SubAreaBiomeResolver
{
    /**
     * @param array<int, MapLocation> $locations Maps locations indexed by map id
     * @param array<int, MapResult> $results Maps results indexed by map id
     *
     * @return array<int, SubAreaBiome> Indexed by sub area id, sorted by id
     */
    public function resolve(array $locations, array $results): array
    {
        /** @var array<int, array{location: MapLocation, total: int, classified: int, outdoor: array<string, int>, all: array<string, int>}> $subAreas */
        $subAreas = [];

        foreach ($locations as $mapId => $location) {
            $subArea = &$subAreas[$location->subAreaId];
            $subArea ??= ['location' => $location, 'total' => 0, 'classified' => 0, 'outdoor' => [], 'all' => []];
            ++$subArea['total'];

            $classification = ($results[$mapId] ?? null)?->classification;

            if ($classification === null) {
                continue;
            }

            ++$subArea['classified'];

            if ($classification->biome === null) {
                continue;
            }

            $biome = $classification->biome->value;
            $subArea['all'][$biome] = ($subArea['all'][$biome] ?? 0) + 1;

            if (!$classification->indoor) {
                $subArea['outdoor'][$biome] = ($subArea['outdoor'][$biome] ?? 0) + 1;
            }
        }

        unset($subArea);
        ksort($subAreas);

        return array_map(static function (array $subArea) {
            $outdoorOnly = $subArea['outdoor'] !== [];
            $votes = self::sort($outdoorOnly ? $subArea['outdoor'] : $subArea['all']);

            return new SubAreaBiome(
                id: $subArea['location']->subAreaId,
                name: $subArea['location']->subAreaName,
                areaId: $subArea['location']->areaId,
                areaName: $subArea['location']->areaName,
                biome: $votes ? Biome::from((string) array_key_first($votes)) : null,
                votes: $votes,
                outdoorOnly: $outdoorOnly,
                totalMaps: $subArea['total'],
                classifiedMaps: $subArea['classified'],
            );
        }, $subAreas);
    }

    /**
     * Sort votes by count desc, then by biome declaration order for a deterministic result
     *
     * @param array<string, int> $votes
     * @return array<string, int>
     */
    private static function sort(array $votes): array
    {
        $order = array_flip(array_map(static fn (Biome $biome) => $biome->value, Biome::cases()));

        uksort($votes, static fn (string $a, string $b) => [$votes[$b], $order[$a]] <=> [$votes[$a], $order[$b]]);

        return $votes;
    }
}
