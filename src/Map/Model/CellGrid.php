<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

use Arakne\MapParser\Loader\Map;

use function count;
use function intdiv;

/**
 * Geometry of the cells of a map, with the cell coordinates of the game
 *
 * Cells are stored by lines of (2 * width - 1) cells: a line of width cells, then a line of (width - 1) cells shifted by half a cell.
 * The coordinates follow the axes of the isometric grid: x to the south east (next cell: id + width),
 * y to the south west (next cell: id + width - 1). So on the screen, the next cell on the line (id + 1) is at x + 1, y - 1.
 */
final readonly class CellGrid
{
    /** @var array<int, array<int, int>> Cell ids indexed by x, then y */
    private array $ids;

    public function __construct(
        /** Number of cells of the first line */
        public int $width,
        /** Total number of cells */
        public int $cells,
    ) {
        $ids = [];

        for ($cellId = 0; $cellId < $cells; ++$cellId) {
            [$x, $y] = $this->coordinates($cellId);
            $ids[$x][$y] = $cellId;
        }

        $this->ids = $ids;
    }

    public static function of(Map $map): self
    {
        return new self($map->width, count($map->cells));
    }

    /**
     * @return array{int, int} x and y coordinates of the cell
     */
    public function coordinates(int $cellId): array
    {
        $line = intdiv($cellId, 2 * $this->width - 1);
        $y = $line - ($cellId - $line * (2 * $this->width - 1)) % $this->width;

        return [intdiv($cellId - ($this->width - 1) * $y, $this->width), $y];
    }

    /**
     * Id of the cell at the given coordinates, or null if it is outside the map
     */
    public function cellId(int $x, int $y): ?int
    {
        return $this->ids[$x][$y] ?? null;
    }
}
