<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use MapClassifier\Map\Classification\Model\LocationType;
use Override;

use function array_map;
use function floatval;
use function is_array;
use function is_numeric;
use function ksort;
use function uasort;

/**
 * Usage of a sprite on the maps of one area (see {@see AreaProfile})
 *
 * The usage statistics of the global {@see SpriteProfile}, computed on the maps of the area only, and broken down by
 * location type. The distributions are complete: no value is ignored as noise, the area being small enough.
 * The classification of the sprite (its type) and its neighbors stay global, in the sprites export: the neighbors
 * describe how a sprite is assembled, which hardly depends on the area, and would make the profiles five times larger.
 *
 * JSON: {"layer", "occurrences", "maps", "classified_maps", "biomes": {biome: {@see UsageCount}},
 * "movements": {movement: frequency}, "ground_share": float | null, "bare_share": float | null, "path": {@see PathProximity} | null,
 * "cover_share": float | null, "location_types": {type: {@see AreaSpriteUsage}}}
 */
final readonly class AreaSprite implements JsonSerializable
{
    public function __construct(
        public SpriteSet $set,
        public int $id,
        /** Main layer in the area */
        public Layer $layer,
        /** Total number of cells (or backgrounds) using the sprite in the area */
        public int $occurrences,
        /** Number of maps of the area using the sprite */
        public int $maps,
        /** Number of these maps that are classified */
        public int $classifiedMaps,
        /** @var array<string, UsageCount> Indexed by biome value, sorted by number of maps desc */
        public array $biomes = [],
        /** @var array<int, float> Frequency of each movement of the cells using the sprite, indexed by movement */
        public array $movements = [],
        public ?float $groundShare = null,
        public ?float $bareShare = null,
        public ?PathProximity $path = null,
        public ?float $coverShare = null,
        /** @var array<string, AreaSpriteUsage> Usage on the classified maps of each location type, indexed by {@see LocationType} value */
        public array $byLocationType = [],
    ) {}

    /**
     * @param array<string, SpriteStats> $byLocationType Stats on the maps of each location type, indexed by its value
     */
    public static function of(SpriteSet $set, int $id, SpriteStats $stats, array $byLocationType): self
    {
        $layer = $stats->mainLayer();
        $biomes = $stats->biomes;
        uasort($biomes, static fn (UsageCount $a, UsageCount $b) => $b->maps <=> $a->maps);
        ksort($byLocationType);

        return new self(
            set: $set,
            id: $id,
            layer: $layer,
            occurrences: $stats->occurrences(),
            maps: $stats->maps,
            classifiedMaps: $stats->classifiedMaps,
            biomes: $biomes,
            movements: $stats->movementFrequencies(),
            groundShare: $layer === Layer::Ground ? $stats->groundShare() : null,
            bareShare: $layer === Layer::Background ? $stats->bareShare() : null,
            path: $stats->pathProximity(),
            coverShare: $stats->coverShare(),
            byLocationType: array_map(static fn (SpriteStats $type) => AreaSpriteUsage::of($type, $layer), $byLocationType),
        );
    }

    /**
     * Usage on the maps of a location type, null if the sprite is not used there
     */
    public function on(LocationType $type): ?AreaSpriteUsage
    {
        return $this->byLocationType[$type->value] ?? null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(SpriteSet $set, int $id, array $data): self
    {
        $array = static fn (mixed $value): array => is_array($value) ? $value : [];

        return new self(
            set: $set,
            id: $id,
            layer: Layer::from((string) ($data['layer'] ?? '')),
            occurrences: (int) ($data['occurrences'] ?? 0),
            maps: (int) ($data['maps'] ?? 0),
            classifiedMaps: (int) ($data['classified_maps'] ?? 0),
            biomes: array_map(UsageCount::fromJson(...), $array($data['biomes'] ?? null)),
            movements: array_map(floatval(...), $array($data['movements'] ?? null)),
            groundShare: is_numeric($data['ground_share'] ?? null) ? (float) $data['ground_share'] : null,
            bareShare: is_numeric($data['bare_share'] ?? null) ? (float) $data['bare_share'] : null,
            path: is_array($data['path'] ?? null) ? PathProximity::fromArray($data['path']) : null,
            coverShare: is_numeric($data['cover_share'] ?? null) ? (float) $data['cover_share'] : null,
            byLocationType: array_map(static fn (mixed $value) => AreaSpriteUsage::fromArray($array($value)), $array($data['location_types'] ?? null)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'layer' => $this->layer->value,
            'occurrences' => $this->occurrences,
            'maps' => $this->maps,
            'classified_maps' => $this->classifiedMaps,
            'biomes' => (object) $this->biomes,
            'movements' => (object) $this->movements,
            'ground_share' => $this->groundShare,
            'bare_share' => $this->bareShare,
            'path' => $this->path,
            'cover_share' => $this->coverShare,
            'location_types' => (object) $this->byLocationType,
        ];
    }
}
