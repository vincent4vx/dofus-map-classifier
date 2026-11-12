<?php

declare(strict_types=1);

namespace MapClassifier\Lang;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sub_area' => ['id' => $this->subAreaId, 'name' => $this->subAreaName],
            'area' => $this->areaId === null ? null : ['id' => $this->areaId, 'name' => $this->areaName],
            'super_area' => $this->superAreaId === null ? null : ['id' => $this->superAreaId, 'name' => $this->superAreaName],
        ];
    }
}
