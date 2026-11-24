<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model\Area;

use JsonSerializable;
use MapClassifier\Sprite\Model\AreaMaps;
use Override;

/**
 * Summary of an area for the generation of its maps: for each location type, its terrain and its paths (areas.json)
 *
 * JSON: {"id": int, "name": string | null, "maps": {@see AreaMaps}, "location_types": {type: {@see LocationOverview}}}
 */
final readonly class AreaOverview implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?string $name,
        public AreaMaps $maps,
        /** @var array<string, LocationOverview> Indexed by location type value */
        public array $locationTypes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'maps' => $this->maps, 'location_types' => (object) $this->locationTypes];
    }
}
