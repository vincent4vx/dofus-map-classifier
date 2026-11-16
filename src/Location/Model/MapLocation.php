<?php

declare(strict_types=1);

namespace MapClassifier\Location\Model;

/**
 * Location of a map in the world (sub area, area and super area), extracted from the "maps" lang file
 */
final readonly class MapLocation
{
    public function __construct(
        public int $mapId,
        public int $subAreaId,
        public ?string $subAreaName = null,
        public ?int $areaId = null,
        public ?string $areaName = null,
        public ?int $superAreaId = null,
        public ?string $superAreaName = null,
    ) {}

    public function subArea(): NamedLocation
    {
        return new NamedLocation($this->subAreaId, $this->subAreaName);
    }

    public function area(): ?NamedLocation
    {
        return $this->areaId === null ? null : new NamedLocation($this->areaId, $this->areaName);
    }

    public function superArea(): ?NamedLocation
    {
        return $this->superAreaId === null ? null : new NamedLocation($this->superAreaId, $this->superAreaName);
    }
}
