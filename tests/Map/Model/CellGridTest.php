<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Model;

use MapClassifier\Map\Model\CellGrid;
use PHPUnit\Framework\TestCase;

final class CellGridTest extends TestCase
{
    // Default map: lines of 15 cells, then 14 shifted cells, 479 cells
    private CellGrid $grid;

    protected function setUp(): void
    {
        $this->grid = new CellGrid(15, 479);
    }

    public function testCoordinates(): void
    {
        $this->assertSame([0, 0], $this->grid->coordinates(0));
        $this->assertSame([1, -1], $this->grid->coordinates(1), 'Next cell on the line: to the east');
        $this->assertSame([1, 0], $this->grid->coordinates(15), 'First cell of the shifted line: to the south east');
        $this->assertSame([2, 2], $this->grid->coordinates(58));
        $this->assertSame([2, 1], $this->grid->coordinates(44));
    }

    public function testCellId(): void
    {
        for ($cellId = 0; $cellId < 479; ++$cellId) {
            $this->assertSame($cellId, $this->grid->cellId(...$this->grid->coordinates($cellId)));
        }

        // Around the cell 44 (first cell of the second shifted line)
        $this->assertSame(15, $this->grid->cellId(1, 0), 'north');
        $this->assertSame(30, $this->grid->cellId(2, 0), 'north east');
        $this->assertSame(45, $this->grid->cellId(3, 0), 'east');
        $this->assertSame(59, $this->grid->cellId(3, 1), 'south east');
        $this->assertSame(73, $this->grid->cellId(3, 2), 'south');
        $this->assertSame(58, $this->grid->cellId(2, 2), 'south west');
        $this->assertSame(29, $this->grid->cellId(1, 1), 'north west');
        $this->assertNull($this->grid->cellId(1, 2), 'west: out of the map');
    }

    public function testCellIdOutOfTheMap(): void
    {
        $this->assertNull($this->grid->cellId(-1, 0));
        $this->assertNull($this->grid->cellId(0, 1), 'Left of the first line');
        $this->assertNull($this->grid->cellId(15, -15), 'Right of the first line');
        $this->assertNull($this->grid->cellId(16, 17), 'Below the last line');
    }
}
