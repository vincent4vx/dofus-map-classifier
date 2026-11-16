<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Model\SubAreaBiome;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\LocationUsage;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;

use function array_filter;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_sum;
use function arsort;
use function ksort;
use function reset;

/**
 * Aggregate maps data on the sprites they use
 *
 * Usage:
 * ```php
 * $analyzer = new SpriteAnalyzer($locations, $results, $subAreaBiomes);
 * foreach ($maps as $map) {
 *     $analyzer->add($map->id, $extractor->extract($map));
 * }
 * $profiles = $analyzer->profiles();
 * ```
 *
 * Map attributes are counted once per map (not per cell), so a sprite repeated on a single map does not weight more.
 * The biome of a map is its classified biome, or the biome of its sub area if the map has no biome or is not classified.
 * Indoor and location type are only taken from classified maps.
 */
final class SpriteAnalyzer
{
    /**
     * Stats indexed by sprite set, then sprite id
     *
     * @var array<string, array<int, array{
     *     layers: array<string, int>,
     *     maps: int,
     *     classified: int,
     *     areas: array<int, int>,
     *     subAreas: array<int, int>,
     *     biomes: array<string, int>,
     *     indoor: array<string, int>,
     *     locationTypes: array<string, int>,
     * }>>
     */
    private array $stats = [];

    /** @var array<int, string|null> */
    private array $areaNames = [];
    /** @var array<int, string|null> */
    private array $subAreaNames = [];

    public function __construct(
        /** @var array<int, MapLocation> Indexed by map id */
        private readonly array $locations,
        /** @var array<int, MapResult> Indexed by map id */
        private readonly array $results,
        /** @var array<int, SubAreaBiome> Indexed by sub area id */
        private readonly array $subAreaBiomes = [],
        /** Values with a count lower than this ratio of the main value count are ignored */
        private readonly float $minRatio = 0.1,
        /** @var array<int, InteractiveObject> Interactive objects indexed by sprite number of the objects set */
        private readonly array $interactiveObjects = [],
    ) {}

    /**
     * Add the sprites used by a map
     *
     * @param array<string, array<int, int>> $layers Occurrences of each sprite, indexed by layer (see {@see MapSpriteExtractor::extract()})
     */
    public function add(int $mapId, array $layers): void
    {
        $location = $this->locations[$mapId] ?? null;
        $classification = ($this->results[$mapId] ?? null)?->classification;
        $biome = $classification->biome
            ?? ($location !== null ? ($this->subAreaBiomes[$location->subAreaId] ?? null)?->biome : null);

        if ($location !== null) {
            $this->subAreaNames[$location->subAreaId] = $location->subAreaName;

            if ($location->areaId !== null) {
                $this->areaNames[$location->areaId] = $location->areaName;
            }
        }

        /** @var array<string, array<int, true>> $seen Sprites already counted for this map */
        $seen = [];

        foreach ($layers as $layerName => $sprites) {
            $set = Layer::from($layerName)->spriteSet()->value;

            foreach ($sprites as $id => $count) {
                $this->stats[$set][$id] ??= [
                    'layers' => [],
                    'maps' => 0,
                    'classified' => 0,
                    'areas' => [],
                    'subAreas' => [],
                    'biomes' => [],
                    'indoor' => [],
                    'locationTypes' => [],
                ];
                $stats = &$this->stats[$set][$id];
                $stats['layers'][$layerName] = ($stats['layers'][$layerName] ?? 0) + $count;

                // A sprite can be used on several layers of the same map: count map attributes once
                if (isset($seen[$set][$id])) {
                    continue;
                }

                $seen[$set][$id] = true;
                ++$stats['maps'];

                if ($location !== null) {
                    self::increment($stats['subAreas'], $location->subAreaId);

                    if ($location->areaId !== null) {
                        self::increment($stats['areas'], $location->areaId);
                    }
                }

                if ($biome !== null) {
                    self::increment($stats['biomes'], $biome->value);
                }

                if ($classification !== null) {
                    ++$stats['classified'];
                    self::increment($stats['indoor'], $classification->indoor ? 'indoor' : 'outdoor');
                    self::increment($stats['locationTypes'], $classification->locationType->value);
                }
            }
        }

        unset($stats);
    }

    /**
     * Profiles of all the sprites, sorted by set then id
     */
    public function profiles(): SpriteProfiles
    {
        $profiles = [];

        foreach ($this->stats as $set => $sprites) {
            ksort($sprites);

            foreach ($sprites as $id => $stats) {
                $layers = $stats['layers'];
                arsort($layers);

                $spriteSet = SpriteSet::from($set);
                $profiles[] = new SpriteProfile(
                    set: $spriteSet,
                    id: $id,
                    layer: Layer::from((string) array_key_first($layers)),
                    occurrences: array_sum($layers),
                    maps: $stats['maps'],
                    classifiedMaps: $stats['classified'],
                    areas: $this->named($stats['areas'], $this->areaNames),
                    subAreas: $this->named($stats['subAreas'], $this->subAreaNames),
                    biomes: $this->filter($stats['biomes']),
                    indoor: $this->filter($stats['indoor']),
                    locationTypes: $this->filter($stats['locationTypes']),
                    interactive: $spriteSet === SpriteSet::Objects ? ($this->interactiveObjects[$id] ?? null) : null,
                );
            }
        }

        return SpriteProfiles::of($profiles);
    }

    /**
     * Remove values much less frequent than the main one, and sort by count desc
     *
     * @template K of array-key
     * @param array<K, int> $counts
     * @return array<K, int>
     */
    public function filter(array $counts): array
    {
        if (!$counts) {
            return [];
        }

        arsort($counts);
        $min = reset($counts) * $this->minRatio;

        return array_filter($counts, static fn (int $count) => $count >= $min);
    }

    /**
     * @param array<int, int> $counts
     * @param array<int, string|null> $names
     * @return list<LocationUsage>
     */
    private function named(array $counts, array $names): array
    {
        return array_map(
            static fn (int $id, int $count) => new LocationUsage($id, $names[$id] ?? null, $count),
            array_keys($filtered = $this->filter($counts)),
            $filtered,
        );
    }

    /**
     * @param array<array-key, int> $counts
     */
    private static function increment(array &$counts, int|string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
}
