<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

use function array_filter;
use function array_map;
use function count;

/**
 * Export of all map results, with their location and the sub areas biomes
 */
final readonly class MapExport
{
    public function __construct(
        /** @var array<string, array<string, mixed>> Exported maps, indexed by map id */
        public array $maps,
        /** @var array<int, SubAreaBiome> Indexed by sub area id. Empty if locations are not available */
        public array $subAreas,
        /** @var array<string, int> Number of classified maps per biome ("(none)" for maps without biome), sorted by count */
        public array $biomes,
        /** Number of maps in error */
        public int $errors,
    ) {}

    /**
     * Number of sub areas with a resolved biome
     */
    public function resolvedSubAreas(): int
    {
        return count(array_filter($this->subAreas, static fn (SubAreaBiome $subArea) => $subArea->biome !== null));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function subAreasToArray(): array
    {
        return array_map(static fn (SubAreaBiome $subArea) => $subArea->toArray(), $this->subAreas);
    }
}
