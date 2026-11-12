<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use InvalidArgumentException;
use MapClassifier\Lang\MapLocation;
use MapClassifier\Lang\MapLocationRepository;
use MapClassifier\Map\MapFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function array_filter;
use function preg_match;

/**
 * Filter maps from command line ids: "10000", "100-200", and optionally by location (super area, area, sub area)
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
     * Configure the "ids" argument and location options on the command
     */
    public static function configure(Command $command, string $idsDescription): void
    {
        $command
            ->addArgument('ids', InputArgument::IS_ARRAY, $idsDescription)
            ->addOption('super-area', null, InputOption::VALUE_REQUIRED, 'Only maps of this super area id (e.g. 3 for Incarnam)')
            ->addOption('area', null, InputOption::VALUE_REQUIRED, 'Only maps of this area id')
            ->addOption('sub-area', null, InputOption::VALUE_REQUIRED, 'Only maps of this sub area id')
        ;
    }

    /**
     * Create the selection from the command input
     */
    public static function fromInput(InputInterface $input, MapLocationRepository $locations): self
    {
        $selection = self::parse($input->getArgument('ids'));
        $option = static fn (string $name) => $input->getOption($name) !== null ? (int) $input->getOption($name) : null;

        return $selection->withLocation($locations, $option('super-area'), $option('area'), $option('sub-area'));
    }

    /**
     * Only keep maps matching the given location
     * Note: maps without known location are excluded when a filter is set
     */
    public function withLocation(MapLocationRepository $repository, ?int $superArea = null, ?int $area = null, ?int $subArea = null): self
    {
        if ($superArea === null && $area === null && $subArea === null) {
            return $this;
        }

        return new self($this->ranges, array_filter(
            $repository->all(),
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
