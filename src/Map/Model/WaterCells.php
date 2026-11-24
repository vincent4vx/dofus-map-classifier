<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

use Arakne\MapParser\Loader\Map;

/**
 * Cells of a map showing water: a water ground, or no ground over a water background
 */
final readonly class WaterCells
{
    private function __construct(
        private CellGrid $grid,
        /** @var array<int, int> Water sprite shown by each water cell (ground, or background), indexed by cell id */
        private array $fills,
    ) {}

    /**
     * @param array<int, true> $waterGrounds Ground sprites (and backgrounds) drawing water, indexed by id
     */
    public static function of(Map $map, CellGrid $grid, array $waterGrounds): self
    {
        $background = isset($waterGrounds[$map->background]) ? $map->background : null;
        $fills = [];

        foreach ($map->cells as $cellId => $cell) {
            if (!$cell->active) {
                continue;
            }

            $ground = $cell->ground->number;

            if ($ground > 0 ? isset($waterGrounds[$ground]) : $background !== null) {
                $fills[$cellId] = $ground > 0 ? $ground : (int) $background;
            }
        }

        return new self($grid, $fills);
    }

    /**
     * The map shows water on at least one cell
     */
    public function exists(): bool
    {
        return $this->fills !== [];
    }

    /**
     * Water sprite shown by the cell, or null if it is a land cell
     */
    public function fill(int $cellId): ?int
    {
        return $this->fills[$cellId] ?? null;
    }

    /**
     * Directions of the land cells around the cell. The cells outside the map are unknown: they are not counted as land
     *
     * @return list<Direction>
     */
    public function landAround(int $cellId): array
    {
        [$x, $y] = $this->grid->coordinates($cellId);
        $land = [];

        foreach (Direction::cases() as $direction) {
            [$dx, $dy] = $direction->offset();
            $neighbor = $this->grid->cellId($x + $dx, $y + $dy);

            if ($neighbor !== null && !isset($this->fills[$neighbor])) {
                $land[] = $direction;
            }
        }

        return $land;
    }
}
