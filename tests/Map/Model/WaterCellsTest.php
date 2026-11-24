<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Model;

use Arakne\MapParser\Loader\Map;
use Arakne\MapParser\Parser\CellDataParser;
use MapClassifier\Map\Model\CellGrid;
use MapClassifier\Map\Model\Direction;
use MapClassifier\Map\Model\WaterCells;
use PHPUnit\Framework\TestCase;

use function array_fill;
use function implode;

final class WaterCellsTest extends TestCase
{
    private const string WATER = 'GhySeaaaaa';
    private const string LAND = 'Ghybeaaaaa';
    private const string BARE = 'Hhaaeaaaaa';

    public function testLandAroundAWaterCell(): void
    {
        // A 15 x 17 map of water (ground 44), with land on some cells around the cell 200
        $cells = array_fill(0, 479, self::WATER);
        $cells[201] = self::LAND; // next cell of the row: on the right
        $cells[185] = self::LAND; // previous half row, on the left: north west
        $cells[171] = self::LAND; // two half rows up: north
        $map = new Map(1, 15, 17, 0, new CellDataParser()->parse(implode('', $cells)));
        $water = WaterCells::of($map, CellGrid::of($map), [44 => true]);

        $this->assertTrue($water->exists());
        $this->assertSame(44, $water->fill(200));
        $this->assertNull($water->fill(201), 'A land cell');
        $this->assertSame([Direction::North, Direction::East, Direction::NorthWest], $water->landAround(200));
        $this->assertSame('n e nw', Direction::key($water->landAround(200)));
        $this->assertSame([], $water->landAround(0), 'The cells outside the map are not land');
    }

    public function testBareCellsShowAWaterBackground(): void
    {
        $cells = array_fill(0, 479, self::BARE);
        $cells[200] = self::LAND;
        $map = new Map(1, 15, 17, 44, new CellDataParser()->parse(implode('', $cells)));

        $water = WaterCells::of($map, CellGrid::of($map), [44 => true]);
        $this->assertSame(44, $water->fill(0), 'No ground: the water background shows');
        $this->assertNull($water->fill(200));
        $this->assertSame([Direction::SouthEast], $water->landAround(185));

        $this->assertFalse(WaterCells::of($map, CellGrid::of($map), [45 => true])->exists(), 'Another background is not water');
    }
}
