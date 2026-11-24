<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\MapSpriteUsage;
use MapClassifier\Sprite\Model\Orientation;
use MapClassifier\Sprite\Model\SpriteExample;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Model\SpriteStats;
use PHPUnit\Framework\TestCase;

final class SpriteStatsTest extends TestCase
{
    public function testMainLayerAndOccurrences(): void
    {
        $stats = new SpriteStats();
        $stats->addMap(self::usage([Layer::Layer2, Layer::Layer1, Layer::Layer2]), null, null, null);
        $stats->addMap(self::usage([Layer::Layer1]), null, null, null);

        $this->assertSame(Layer::Layer2, $stats->mainLayer(), 'Equality: last layer of the enum, whatever the reading order');
        $this->assertSame(4, $stats->occurrences());
        $this->assertSame(2, $stats->maps);

        $stats->addMap(self::usage([Layer::Layer1]), null, null, null);
        $this->assertSame(Layer::Layer1, $stats->mainLayer());

        $ground = new SpriteStats();
        $ground->addMap(self::usage([Layer::Background, Layer::Ground]), null, null, null);
        $this->assertSame(Layer::Ground, $ground->mainLayer());
    }

    public function testExample(): void
    {
        $stats = new SpriteStats();
        $this->assertNull($stats->example(Layer::Layer2));

        $map = static function (array $cells): MapSpriteUsage {
            $usage = new MapSpriteUsage(SpriteSet::Objects, 1);

            foreach ($cells as $cellId => $distance) {
                $usage->addCell(Layer::Layer2, cellId: $cellId, centerDistance: $distance, orientation: new Orientation(0, $cellId === 21));
            }

            return $usage;
        };

        $stats->addMap($map([10 => 50.0]), null, null, null, 1);
        $this->assertEquals(new SpriteExample(1, 10), $stats->example(Layer::Layer2));

        $stats->addMap($map([20 => 300.0, 21 => 100.0, 22 => 200.0]), null, null, null, 2);
        $this->assertEquals(new SpriteExample(2, 22), $stats->example(Layer::Layer2), 'Map with the most uses, cell the closest to the center not flipped');

        $stats->addMap($map([30 => 90.0, 31 => 500.0, 32 => 400.0]), null, null, null, 3);
        $this->assertEquals(new SpriteExample(3, 30), $stats->example(Layer::Layer2), 'Same number of uses: the closest to the center');

        $stats->addMap($map([40 => 300.0, 41 => 90.0, 42 => 400.0]), null, null, null, 4);
        $this->assertEquals(new SpriteExample(3, 30), $stats->example(Layer::Layer2), 'Equality: the first map is kept');

        $stats->addMap($map([50 => 0.0, 51 => 0.0, 52 => 0.0, 53 => 0.0]), null, null, null);
        $this->assertEquals(new SpriteExample(3, 30), $stats->example(Layer::Layer2), 'Without map id: not used as example');
        $this->assertNull($stats->example(Layer::Layer1));

        $flipped = new SpriteStats();
        $usage = new MapSpriteUsage(SpriteSet::Objects, 1);
        $usage->addCell(Layer::Layer2, cellId: 1, centerDistance: 300.0, orientation: new Orientation(0, true));
        $usage->addCell(Layer::Layer2, cellId: 2, centerDistance: 100.0, orientation: new Orientation(0, true));
        $flipped->addMap($usage, null, null, null, 5);
        $this->assertEquals(new SpriteExample(5, 2, new Orientation(0, true)), $flipped->example(Layer::Layer2), 'Only flipped uses: the closest to the center');
    }

    public function testGroundShare(): void
    {
        $stats = new SpriteStats();
        $this->assertNull($stats->groundShare());

        $stats->addMap(self::usage([Layer::Ground, Layer::Ground]), null, null, null, mapGroundCells: 8);
        $stats->addMap(self::usage([Layer::Ground]), null, null, null, mapGroundCells: 2);
        $stats->addMap(self::usage([Layer::Background]), null, null, null, mapGroundCells: 10);

        $this->assertSame(0.375, $stats->groundShare(), 'Average of 25% and 50%, the map using it as background is ignored');
    }

    public function testBareShare(): void
    {
        $stats = new SpriteStats();
        $this->assertNull($stats->bareShare());

        $stats->addMap(self::usage([Layer::Background]), null, null, null, mapBareShare: 0.9);
        $stats->addMap(self::usage([Layer::Background, Layer::Ground]), null, null, null, mapBareShare: 0.5);
        $stats->addMap(self::usage([Layer::Ground]), null, null, null, mapBareShare: 0.0);

        $this->assertSame(0.7, $stats->bareShare(), 'Average of 90% and 50%, the map using it only as ground is ignored');
    }

    public function testCoverShare(): void
    {
        $stats = new SpriteStats();
        $this->assertNull($stats->coverShare(), 'Never used as an object');

        // A large patch covering 120 of the 480 cells of a map, then 240 cells over two uses on another map
        $first = self::usage([Layer::Layer1]);
        $first->addCover(range(0, 119));
        $stats->addMap($first, null, null, null, mapCells: 480);
        $second = self::usage([Layer::Layer1, Layer::Layer1]);
        $second->addCover(range(0, 159));
        $second->addCover(range(80, 239));
        $stats->addMap($second, null, null, null, mapCells: 480);
        // A ground covers no cell: the map is ignored
        $stats->addMap(self::usage([Layer::Ground]), null, null, null, mapCells: 480);

        $this->assertSame(0.375, $stats->coverShare(), 'Average of 25% and 50%: overlapping uses count their cells once');
    }

    public function testSlope(): void
    {
        $stats = new SpriteStats();
        $stats->addMap(self::usage([Layer::Ground]), null, null, null);
        $this->assertSame(0, $stats->slopedCells);

        $sloped = new MapSpriteUsage(SpriteSet::Grounds, 1);
        $sloped->addCell(Layer::Ground, true);
        $stats->addMap($sloped, null, null, null);
        $this->assertSame(1, $stats->slopedCells);
    }

    /**
     * @param list<Layer> $cells
     */
    private static function usage(array $cells): MapSpriteUsage
    {
        $usage = new MapSpriteUsage($cells[0]->spriteSet(), 1);

        foreach ($cells as $layer) {
            $usage->addCell($layer);
        }

        return $usage;
    }
}
