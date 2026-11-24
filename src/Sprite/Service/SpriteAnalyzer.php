<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Service;

use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Classification\Model\SubAreaBiome;
use MapClassifier\Sprite\Model\AreaMaps;
use MapClassifier\Sprite\Model\AreaProfile;
use MapClassifier\Sprite\Model\AreaStats;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\LocationUsage;
use MapClassifier\Sprite\Model\MapSprites;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\SpriteStats;
use MapClassifier\Sprite\Model\UsageCount;
use MapClassifier\Sprite\Repository\SpriteRepository;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function ksort;
use function reset;
use function uasort;

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
    /** @var array<string, array<int, SpriteStats>> Stats indexed by sprite set value, then sprite id */
    private array $stats = [];

    /** @var array<int, AreaStats> Stats of the maps of each area, indexed by area id */
    private array $areas = [];

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
        /** Exported sprites, used to check the frames of the ground sprites used on slopes. Without it, no sprite is flagged as slope */
        private readonly ?SpriteRepository $sprites = null,
        /**
         * @var array<int, true> Object sprites drawing a floor on layer 1 (classified with the ground usage), as keys: the maps
         *     they cover almost entirely are counted in the area profiles (see {@see AreaMaps::$locationTypes})
         */
        private readonly array $floorObjects = [],
    ) {}

    /**
     * Add the sprites used by a map
     *
     */
    public function add(int $mapId, MapSprites $sprites): void
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

        if ($location?->areaId !== null) {
            ($this->areas[$location->areaId] ??= new AreaStats($location->areaId, $location->areaName))
                ->addMap($sprites, $location, $biome, $classification, $sprites->floorShare($this->floorObjects));
        }

        // One usage per sprite: map attributes are counted once per map
        foreach ($sprites as $usage) {
            $this->stats[$usage->set->value][$usage->id] ??= new SpriteStats();
            $this->stats[$usage->set->value][$usage->id]->addMap($usage, $location, $biome, $classification, $mapId, $sprites->groundCells, $sprites->bareShare(), $sprites->cells);
        }
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
                $spriteSet = SpriteSet::from($set);
                $layer = $stats->mainLayer();
                $profiles[] = new SpriteProfile(
                    set: $spriteSet,
                    id: $id,
                    layer: $layer,
                    occurrences: $stats->occurrences(),
                    maps: $stats->maps,
                    classifiedMaps: $stats->classifiedMaps,
                    areas: $this->named($stats->areas, $this->areaNames),
                    subAreas: $this->named($stats->subAreas, $this->subAreaNames),
                    biomes: $this->filter($stats->biomes),
                    indoor: $this->filter($stats->indoor),
                    locationTypes: $this->filter($stats->locationTypes),
                    interactive: $spriteSet === SpriteSet::Objects ? ($this->interactiveObjects[$id] ?? null) : null,
                    slope: $this->slope($spriteSet, $id, $stats),
                    movements: $stats->movementFrequencies(),
                    neighbors: $stats->neighbors($layer),
                    example: $stats->example($layer),
                    groundShare: $layer === Layer::Ground ? $stats->groundShare() : null,
                    path: $stats->pathProximity(),
                    bareShare: $layer === Layer::Background ? $stats->bareShare() : null,
                    coverShare: $stats->coverShare(),
                    shore: $layer === Layer::Layer1 || $layer === Layer::Layer2 ? $stats->shore() : null,
                );
            }
        }

        return SpriteProfiles::of($profiles);
    }

    /**
     * Profiles of the areas: the usage of the sprites on the maps of each area, sorted by area id
     *
     * @return list<AreaProfile>
     */
    public function areaProfiles(): array
    {
        ksort($this->areas);

        return array_values(array_map(static fn (AreaStats $area) => $area->profile(), $this->areas));
    }

    /**
     * Remove values used on much less maps than the main one, and sort by number of maps desc
     *
     * @template K of array-key
     * @param array<K, UsageCount> $counts
     * @return array<K, UsageCount>
     */
    public function filter(array $counts): array
    {
        if (!$counts) {
            return [];
        }

        uasort($counts, static fn (UsageCount $a, UsageCount $b) => $b->maps <=> $a->maps);
        $min = reset($counts)->maps * $this->minRatio;

        return array_filter($counts, static fn (UsageCount $count) => $count->maps >= $min);
    }

    /**
     * @param array<int, UsageCount> $counts
     * @param array<int, string|null> $names
     * @return list<LocationUsage>
     */
    private function named(array $counts, array $names): array
    {
        return array_map(
            static fn (int $id, UsageCount $count) => new LocationUsage($id, $names[$id] ?? null, $count->maps, $count->occurrences),
            array_keys($filtered = $this->filter($counts)),
            $filtered,
        );
    }

    /**
     * The ground sprite is drawn on slopes: used on at least one sloped cell, and has one frame per slope value
     * The frames are given by the metadata of the exported sprite: an unexported sprite is not flagged
     */
    private function slope(SpriteSet $set, int $id, SpriteStats $stats): bool
    {
        return $set === SpriteSet::Grounds
            && $stats->slopedCells > 0
            && ($this->sprites?->get($set, $id)?->hasSlopeFrames() ?? false);
    }
}
