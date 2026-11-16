<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

use JsonSerializable;
use MapClassifier\Location\Model\NamedLocation;
use Override;

/**
 * Biome of a sub area, deduced from the classification of its maps
 *
 * JSON (sub_areas.json): {"id", "name", "area": {"id", "name"} | null, "biome", "votes": {biome: maps}, "outdoor_only", "total_maps", "classified_maps"}
 */
final readonly class SubAreaBiome implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?NamedLocation $area,
        /** Most frequent biome of the sub area maps, or null if no map with a biome is classified */
        public ?Biome $biome,
        /** @var array<string, int> Number of maps per biome used for the vote, sorted by count */
        public array $votes,
        /** True if only outdoor maps were used for the vote */
        public bool $outdoorOnly,
        /** Number of maps of the sub area declared in the lang */
        public int $totalMaps,
        /** Number of successfully classified maps of the sub area */
        public int $classifiedMaps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'area' => $this->area,
            'biome' => $this->biome?->value,
            'votes' => (object) $this->votes,
            'outdoor_only' => $this->outdoorOnly,
            'total_maps' => $this->totalMaps,
            'classified_maps' => $this->classifiedMaps,
        ];
    }
}
