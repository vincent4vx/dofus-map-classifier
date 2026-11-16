<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Model;

use InvalidArgumentException;
use MapClassifier\Location\Model\MapLocation;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\MapSelection;
use PHPUnit\Framework\TestCase;

use function array_keys;

final class MapSelectionTest extends TestCase
{
    public function testIds(): void
    {
        $selection = MapSelection::parse(['10', '100-200']);

        $this->assertTrue($selection->matches(10));
        $this->assertTrue($selection->matches(150));
        $this->assertFalse($selection->matches(11));
        $this->assertTrue(MapSelection::parse([])->matches(11));
    }

    public function testLocation(): void
    {
        $locations = [
            1 => new MapLocation(1, 10, 'Sub', 5, 'Area', 3, 'Incarnam'),
            2 => new MapLocation(2, 11, 'Sub', 6, 'Area', 0, 'Amakna'),
        ];

        $selection = MapSelection::parse([])->withLocation($locations, superArea: 3);

        $this->assertTrue($selection->matches(1));
        $this->assertFalse($selection->matches(2));
        $this->assertFalse($selection->matches(3), 'Maps without location are excluded');
        $this->assertTrue(MapSelection::parse([])->withLocation($locations)->matches(3), 'No location filter');

        $maps = [1 => new MapFile(1, 'a', true, 'a.swf'), 2 => new MapFile(2, 'b', true, 'b.swf')];
        $this->assertSame([1], array_keys(MapSelection::parse(['1-2'])->withLocation($locations, subArea: 10)->filter($maps)));
    }

    public function testInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MapSelection::parse(['abc']);
    }
}
