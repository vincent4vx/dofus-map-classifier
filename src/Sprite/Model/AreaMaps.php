<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Model;

use JsonSerializable;
use MapClassifier\Map\Classification\Model\LocationType;
use Override;

use function array_map;
use function intval;
use function is_array;

/**
 * Number of maps of an area, overall and by attribute: the denominators of the sprite usages of the area
 *
 * JSON: {"total": int, "classified": int, "with_path": int, "indoor": {"indoor" | "outdoor": int},
 * "biomes": {biome: int}, "location_types": {type: {"maps": int, "with_path": int}}}
 */
final readonly class AreaMaps implements JsonSerializable
{
    public function __construct(
        /** Analyzed maps of the area */
        public int $total = 0,
        /** Classified maps among them */
        public int $classified = 0,
        /** Maps with a preferred path (see {@see \MapClassifier\Map\Model\PreferredPath}) */
        public int $withPath = 0,
        /** @var array<string, int> Classified maps, indexed by "indoor" or "outdoor" */
        public array $indoor = [],
        /** @var array<string, int> Maps indexed by biome value (the map biome, or else the one of its sub area) */
        public array $biomes = [],
        /** @var array<string, array{maps: int, with_path: int}> Classified maps, indexed by location type value */
        public array $locationTypes = [],
    ) {}

    /**
     * Number of classified maps of a location type
     */
    public function of(LocationType $type): int
    {
        return $this->locationTypes[$type->value]['maps'] ?? 0;
    }

    /**
     * Number of classified maps of a location type having a preferred path
     */
    public function withPathOf(LocationType $type): int
    {
        return $this->locationTypes[$type->value]['with_path'] ?? 0;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $ints = static fn (mixed $values): array => is_array($values) ? array_map(intval(...), $values) : [];
        $types = [];

        foreach (is_array($data['location_types'] ?? null) ? $data['location_types'] : [] as $type => $counts) {
            $types[(string) $type] = ['maps' => (int) ($counts['maps'] ?? 0), 'with_path' => (int) ($counts['with_path'] ?? 0)];
        }

        return new self(
            (int) ($data['total'] ?? 0),
            (int) ($data['classified'] ?? 0),
            (int) ($data['with_path'] ?? 0),
            $ints($data['indoor'] ?? null),
            $ints($data['biomes'] ?? null),
            $types,
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'total' => $this->total,
            'classified' => $this->classified,
            'with_path' => $this->withPath,
            'indoor' => (object) $this->indoor,
            'biomes' => (object) $this->biomes,
            'location_types' => (object) $this->locationTypes,
        ];
    }
}
