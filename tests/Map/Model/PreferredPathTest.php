<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Model;

use MapClassifier\Map\Model\CellGrid;
use MapClassifier\Map\Model\PreferredPath;
use PHPUnit\Framework\TestCase;

final class PreferredPathTest extends TestCase
{
    public function testDistance(): void
    {
        // Default map: lines of 15 cells, then 14 shifted cells
        $grid = new CellGrid(15, 479, 17);
        // Cell 44 is at (2, 1): a path along x, from (2, 1) to (4, 1)
        $path = new PreferredPath($grid, [44, 59, 74]);

        $this->assertTrue($path->exists());
        $this->assertSame(0, $path->distance(44), 'On the path');
        $this->assertSame(0, $path->distance(74));
        $this->assertSame(1, $path->distance($grid->cellId(3, 2) ?? -1), 'Next to the path');
        $this->assertSame(1, $path->distance($grid->cellId(1, 0) ?? -1), 'Touching the path by a corner');
        $this->assertSame(2, $path->distance($grid->cellId(6, 3) ?? -1), 'Diagonals are one cell');
        $this->assertSame(PreferredPath::FAR, $path->distance($grid->cellId(7, 1) ?? -1));
        $this->assertSame(PreferredPath::FAR, $path->distance(478));
    }

    public function testNoPath(): void
    {
        $path = new PreferredPath(new CellGrid(15, 479, 17), []);

        $this->assertFalse($path->exists());
        $this->assertNull($path->distance(44));
    }
}
