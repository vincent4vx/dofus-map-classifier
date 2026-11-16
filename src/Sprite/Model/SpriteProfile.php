<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function array_map;
use function array_values;
use function is_array;

/**
 * Aggregated data of a sprite, deduced from the maps using it
 *
 * Attributes values are given with the number of maps they come from, sorted by count.
 * Values much less frequent than the main one are considered as noise, and removed.
 *
 * JSON (sprite_profiles.json): {"id", "layer", "occurrences", "maps", "classified_maps",
 * "areas": [{@see LocationUsage}], "sub_areas": [{@see LocationUsage}], "biomes": {biome: maps}, "indoor": {"indoor" | "outdoor": maps},
 * "location_types": {type: maps}, "interactive": {@see InteractiveObject} | null}
 */
final readonly class SpriteProfile implements JsonSerializable
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
        /** @var list<LocationUsage> */
        public array $areas,
        /** @var list<LocationUsage> */
        public array $subAreas,
        /** @var array<string, int> */
        public array $biomes,
        /** @var array<string, int> "indoor" or "outdoor" */
        public array $indoor,
        /** @var array<string, int> */
        public array $locationTypes,
        /** Interactive object (resource, workshop…) drawn by the sprite, from the langs */
        public ?InteractiveObject $interactive = null,
    ) {}

    /**
     * Create from the JSON of {@see jsonSerialize()}
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(SpriteSet $set, array $data): self
    {
        $named = static fn (mixed $values) => array_values(array_map(
            static fn (mixed $value) => LocationUsage::fromArray(is_array($value) ? $value : []),
            is_array($values) ? $values : [],
        ));
        $counts = static fn (mixed $values) => array_map('intval', is_array($values) ? $values : []);

        return new self(
            set: $set,
            id: (int) ($data['id'] ?? 0),
            layer: Layer::from((string) ($data['layer'] ?? '')),
            occurrences: (int) ($data['occurrences'] ?? 0),
            maps: (int) ($data['maps'] ?? 0),
            classifiedMaps: (int) ($data['classified_maps'] ?? 0),
            areas: $named($data['areas'] ?? null),
            subAreas: $named($data['sub_areas'] ?? null),
            biomes: $counts($data['biomes'] ?? null),
            indoor: $counts($data['indoor'] ?? null),
            locationTypes: $counts($data['location_types'] ?? null),
            interactive: is_array($data['interactive'] ?? null) ? InteractiveObject::fromArray($data['interactive']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
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
            'interactive' => $this->interactive,
        ];
    }
}
