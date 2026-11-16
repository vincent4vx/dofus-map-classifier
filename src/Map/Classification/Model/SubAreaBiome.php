<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

/**
 * Biome of a sub area, deduced from the classification of its maps
 */
final readonly class SubAreaBiome
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $areaId,
        public ?string $areaName,
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
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'area' => $this->areaId === null ? null : ['id' => $this->areaId, 'name' => $this->areaName],
            'biome' => $this->biome?->value,
            'votes' => (object) $this->votes,
            'outdoor_only' => $this->outdoorOnly,
            'total_maps' => $this->totalMaps,
            'classified_maps' => $this->classifiedMaps,
        ];
    }
}
