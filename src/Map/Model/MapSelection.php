<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

use InvalidArgumentException;
use MapClassifier\Location\Model\MapLocation;

use function array_filter;
use function preg_match;

/**
 * Selection of maps by ids: "10000", "100-200", and optionally by location (super area, area, sub area)
 */
final readonly class MapSelection
{
    private function __construct(
        /** @var list<array{int, int}> */
        private array $ranges,
        /** @var array<int, MapLocation>|null Allowed maps locations, or null to not filter by location */
        private ?array $locations = null,
    ) {}

    /**
     * Only keep maps matching the given location
     * Note: maps without known location are excluded when a filter is set
     *
     * @param array<int, MapLocation> $locations All known locations, indexed by map id
     */
    public function withLocation(array $locations, ?int $superArea = null, ?int $area = null, ?int $subArea = null): self
    {
        if ($superArea === null && $area === null && $subArea === null) {
            return $this;
        }

        return new self($this->ranges, array_filter(
            $locations,
            static fn (MapLocation $location) => ($superArea === null || $location->superAreaId === $superArea)
                && ($area === null || $location->areaId === $area)
                && ($subArea === null || $location->subAreaId === $subArea),
        ));
    }

    /**
     * @param list<string> $arguments
     */
    public static function parse(array $arguments): self
    {
        $ranges = [];

        foreach ($arguments as $argument) {
            if (preg_match('/^(\d+)$/', $argument, $m)) {
                $ranges[] = [(int) $m[1], (int) $m[1]];
            } elseif (preg_match('/^(\d+)-(\d+)$/', $argument, $m)) {
                $ranges[] = [(int) $m[1], (int) $m[2]];
            } else {
                throw new InvalidArgumentException('Invalid map id or range "' . $argument . '" (expected "123" or "100-200")');
            }
        }

        return new self($ranges);
    }

    public function matches(int $id): bool
    {
        if ($this->locations !== null && !isset($this->locations[$id])) {
            return false;
        }

        if (!$this->ranges) {
            return true;
        }

        foreach ($this->ranges as [$min, $max]) {
            if ($id >= $min && $id <= $max) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, MapFile> $maps
     *
     * @return array<int, MapFile>
     */
    public function filter(array $maps): array
    {
        return array_filter($maps, fn (MapFile $map) => $this->matches($map->id));
    }
}
