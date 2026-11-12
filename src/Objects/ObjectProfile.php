<?php

declare(strict_types=1);

namespace MapClassifier\Objects;

/**
 * Aggregated data of a sprite, deduced from the maps using it
 *
 * Attributes values are given with the number of maps they come from, sorted by count.
 * Values much less frequent than the main one are considered as noise, and removed.
 */
final readonly class ObjectProfile
{
    public function __construct(
        public SpriteSet $set,
        public int $id,
        /** Most common layer */
        public Layer $layer,
        /** Total number of cells (or backgrounds) using the sprite */
        public int $occurrences,
        /** Number of maps using the sprite */
        public int $maps,
        /** Number of classified maps using the sprite */
        public int $classifiedMaps,
        /** @var list<array{id: int, name: string|null, maps: int}> */
        public array $areas,
        /** @var list<array{id: int, name: string|null, maps: int}> */
        public array $subAreas,
        /** @var array<string, int> */
        public array $biomes,
        /** @var array<string, int> "indoor" or "outdoor" */
        public array $indoor,
        /** @var array<string, int> */
        public array $locationTypes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'layer' => $this->layer->value,
            'occurrences' => $this->occurrences,
            'maps' => $this->maps,
            'classified_maps' => $this->classifiedMaps,
            'areas' => $this->areas,
            'sub_areas' => $this->subAreas,
            'biomes' => (object) $this->biomes,
            'indoor' => (object) $this->indoor,
            'location_types' => (object) $this->locationTypes,
        ];
    }
}
