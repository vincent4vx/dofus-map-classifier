<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use Override;

use function array_map;
use function floatval;
use function array_values;
use function is_array;
use function is_numeric;

/**
 * Aggregated data of a sprite, deduced from the maps using it
 *
 * Attributes values are given with the number of maps they come from, sorted by count.
 * Values much less frequent than the main one are considered as noise, and removed.
 *
 * JSON (sprite_profiles.json): {"id", "layer", "slope", "occurrences", "maps", "classified_maps",
 * "areas": [{@see LocationUsage}], "sub_areas": [{@see LocationUsage}], "biomes": {biome: {@see UsageCount}}, "indoor": {"indoor" | "outdoor": {@see UsageCount}},
 * "location_types": {type: {@see UsageCount}}, "movements": {movement: frequency}, "ground_share": float | null, "path": {@see PathProximity} | null, "example": {@see SpriteExample} | null, "neighbors": [{@see OrientationNeighbors}], "interactive": {@see InteractiveObject} | null}
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
        /** @var array<string, UsageCount> Indexed by biome value */
        public array $biomes,
        /** @var array<string, UsageCount> Indexed by "indoor" or "outdoor" (classified maps only) */
        public array $indoor,
        /** @var array<string, UsageCount> Indexed by location type value (classified maps only) */
        public array $locationTypes,
        /** Interactive object (resource, workshop…) drawn by the sprite, from the langs */
        public ?InteractiveObject $interactive = null,
        /** Ground sprite drawn on slopes: used on at least one sloped cell (slope other than 0 and 1, i.e. not flat), with one frame per slope value */
        public bool $slope = false,
        /**
         * @var array<int, float> Frequency (0 to 1) of each movement of the cells using the sprite, indexed by movement
         *     (0: not walkable, 1: interactive object, 2 to 7: walkable). Empty for a sprite only used as background
         */
        public array $movements = [],
        /**
         * @var list<OrientationNeighbors> Frequent neighbors on the main layer, for each orientation of the sprite, sorted by number of cells desc.
         *     Empty for a sprite only used as background
         */
        public array $neighbors = [],
        /** Use of the sprite on a map, on its main layer, to show it in context. Null for a background */
        public ?SpriteExample $example = null,
        /** Average ratio (0 to 1) of the ground cells of its maps covered by the sprite. Only for the sprites mainly used as ground */
        public ?float $groundShare = null,
        /** Position of the cells relative to the preferred path, on the maps with one. Null if the sprite is never used on such a map */
        public ?PathProximity $path = null,
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
        $counts = static fn (mixed $values) => array_map(UsageCount::fromJson(...), is_array($values) ? $values : []);

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
            slope: ($data['slope'] ?? false) === true,
            movements: array_map(floatval(...), is_array($data['movements'] ?? null) ? $data['movements'] : []),
            groundShare: is_numeric($data['ground_share'] ?? null) ? (float) $data['ground_share'] : null,
            path: is_array($data['path'] ?? null) ? PathProximity::fromArray($data['path']) : null,
            example: is_array($data['example'] ?? null) ? SpriteExample::fromArray($data['example']) : null,
            neighbors: array_values(array_map(
                static fn (mixed $value) => OrientationNeighbors::fromArray(is_array($value) ? $value : []),
                is_array($data['neighbors'] ?? null) ? $data['neighbors'] : [],
            )),
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
            'slope' => $this->slope,
            'occurrences' => $this->occurrences,
            'maps' => $this->maps,
            'classified_maps' => $this->classifiedMaps,
            'areas' => $this->areas,
            'sub_areas' => $this->subAreas,
            'biomes' => (object) $this->biomes,
            'indoor' => (object) $this->indoor,
            'location_types' => (object) $this->locationTypes,
            'movements' => (object) $this->movements,
            'ground_share' => $this->groundShare,
            'path' => $this->path,
            'example' => $this->example,
            'neighbors' => $this->neighbors,
            'interactive' => $this->interactive,
        ];
    }
}
