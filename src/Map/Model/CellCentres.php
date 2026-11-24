<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

use Arakne\MapParser\Loader\Map;
use Arakne\MapParser\Renderer\CellShape;
use Arakne\MapParser\Renderer\MapRenderer;

use function ceil;
use function floor;

/**
 * Centres of the cells of a map, in game pixels, to find the cells lying under a sprite
 *
 * The cells are grouped by half row (13.5 pixels): a box only scans the rows it spans.
 */
final readonly class CellCentres
{
    /**
     * @param array<int, array<int, array{int, int}>> $rows Centre (x, y) of each cell, indexed by half row, then cell id
     */
    private function __construct(
        private array $rows,
    ) {}

    public static function of(Map $map): self
    {
        $rows = [];

        foreach ($map->cells as $cellId => $_) {
            if (($shape = CellShape::fromCellId($map, $cellId)) === null) {
                continue;
            }

            $rows[(int) floor($shape->y / MapRenderer::CELL_HALF_HEIGHT)][$cellId] = [$shape->x, $shape->y];
        }

        return new self($rows);
    }

    /**
     * Cells whose centre lies inside the box
     *
     * @return list<int> Cell ids
     */
    public function under(Rectangle $box): array
    {
        $cells = [];
        $first = (int) floor($box->y / MapRenderer::CELL_HALF_HEIGHT);
        $last = (int) ceil(($box->y + $box->height) / MapRenderer::CELL_HALF_HEIGHT);

        for ($row = $first; $row <= $last; ++$row) {
            foreach ($this->rows[$row] ?? [] as $cellId => [$x, $y]) {
                if ($x >= $box->x && $x <= $box->x + $box->width && $y >= $box->y && $y <= $box->y + $box->height) {
                    $cells[] = $cellId;
                }
            }
        }

        return $cells;
    }
}
