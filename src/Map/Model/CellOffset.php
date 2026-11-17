<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

use function explode;

/**
 * Relative position of a cell from another, in cell coordinates (see {@see CellGrid})
 */
final readonly class CellOffset
{
    public function __construct(
        /** Number of cells to the south east (negative to the north west) */
        public int $x,
        /** Number of cells to the south west (negative to the north east) */
        public int $y,
    ) {}

    /**
     * Create from {@see key()}
     */
    public static function fromKey(string $key): self
    {
        [$x, $y] = explode(',', $key, 2) + [1 => '0'];

        return new self((int) $x, (int) $y);
    }

    /**
     * Unique key of the offset (e.g. "1,-2")
     */
    public function key(): string
    {
        return $this->x . ',' . $this->y;
    }
}
