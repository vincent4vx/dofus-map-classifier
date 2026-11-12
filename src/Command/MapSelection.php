<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use InvalidArgumentException;
use MapClassifier\Map\MapFile;

use function array_filter;
use function preg_match;

/**
 * Filter maps from command line ids: "10000", "100-200"
 */
final readonly class MapSelection
{
    private function __construct(
        /** @var list<array{int, int}> */
        private array $ranges,
    ) {}

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
