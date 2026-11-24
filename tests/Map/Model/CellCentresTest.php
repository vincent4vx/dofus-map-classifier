<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Model;

use Arakne\MapParser\Loader\Map;
use Arakne\MapParser\Parser\CellDataParser;
use MapClassifier\Map\Model\CellCentres;
use MapClassifier\Map\Model\Rectangle;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class CellCentresTest extends TestCase
{
    public function testCellsUnderABox(): void
    {
        // A flat 15 x 17 map: 479 cells, 53 pixels apart on a row, 13.5 pixels between half rows
        $map = new Map(1, 15, 17, 0, new CellDataParser()->parse(str_repeat('Hhaaeaaaaa', 479)));
        $centres = CellCentres::of($map);

        $this->assertSame([0], $centres->under(new Rectangle(-5, -5, 10, 10)), 'The first cell is at (0, 0)');
        $this->assertSame([0, 1], $centres->under(new Rectangle(-5, -5, 60, 10)), 'The next cell of the row, 53 pixels away');
        $this->assertSame([0, 15], $centres->under(new Rectangle(-5, -5, 40, 20)), 'The first cell of the next half row, at (26, 13)');
        $this->assertSame([], $centres->under(new Rectangle(5, 5, 10, 5)), 'No centre inside');
    }
}
