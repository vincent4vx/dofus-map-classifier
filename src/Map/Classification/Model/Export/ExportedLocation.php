<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model\Export;

use JsonSerializable;
use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Location\Model\NamedLocation;
use MapClassifier\Map\Classification\Model\Biome;
use Override;

/**
 * Location of an exported map
 *
 * JSON: {"sub_area": {"id", "name", "biome"}, "area": {"id", "name"} | null, "super_area": {"id", "name"} | null}
 */
final readonly class ExportedLocation implements JsonSerializable
{
    public function __construct(
        public ExportedSubArea $subArea,
        public ?NamedLocation $area,
        public ?NamedLocation $superArea,
    ) {}

    /**
     * @param Biome|null $subAreaBiome Biome of the sub area
     */
    public static function fromLocation(MapLocation $location, ?Biome $subAreaBiome): self
    {
        return new self(
            new ExportedSubArea($location->subAreaId, $location->subAreaName, $subAreaBiome),
            $location->area(),
            $location->superArea(),
        );
    }

    /**
     * @return array{sub_area: ExportedSubArea, area: NamedLocation|null, super_area: NamedLocation|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'sub_area' => $this->subArea,
            'area' => $this->area,
            'super_area' => $this->superArea,
        ];
    }
}
