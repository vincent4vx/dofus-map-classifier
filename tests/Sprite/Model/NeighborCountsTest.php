<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use MapClassifier\Map\Model\CellOffset;
use MapClassifier\Sprite\Model\Neighbor;
use MapClassifier\Sprite\Model\NeighborCounts;
use MapClassifier\Sprite\Model\Orientation;
use MapClassifier\Sprite\Model\OrientationNeighbors;
use MapClassifier\Sprite\Model\OrientedSprite;
use PHPUnit\Framework\TestCase;

use function array_map;
use function json_decode;
use function json_encode;

final class NeighborCountsTest extends TestCase
{
    public function testResult(): void
    {
        $wall = new OrientedSprite(10);
        $flippedWall = new OrientedSprite(10, new Orientation(0, true));
        $pillar = new OrientedSprite(11, new Orientation(1));

        $counts = new NeighborCounts(new Orientation(0, true));

        for ($i = 0; $i < 30; ++$i) {
            $counts->addCell(['2,0' => $wall, '-2,0' => $i < 2 ? $pillar : $flippedWall]);
        }

        $other = new NeighborCounts(new Orientation(0, true));
        $other->addCell(['-2,0' => $pillar, '0,1' => $wall]);
        $other->addCell(['-2,0' => $pillar]);
        $counts->merge($other);

        $this->assertSame(32, $counts->cells);
        $this->assertEquals(new OrientationNeighbors(new Orientation(0, true), 32, [
            new Neighbor(new CellOffset(2, 0), $wall, 30, 0.9375),
            new Neighbor(new CellOffset(-2, 0), $flippedWall, 28, 0.875),
            new Neighbor(new CellOffset(-2, 0), $pillar, 4, 0.125),
            // 0,1: found once, ignored
        ]), $counts->result());
    }

    public function testRareNeighborsAreIgnored(): void
    {
        $counts = new NeighborCounts(new Orientation());

        for ($i = 0; $i < 100; ++$i) {
            $counts->addCell(['1,0' => new OrientedSprite($i < 9 ? 1 : 2), '-1,0' => new OrientedSprite($i < 10 ? 3 : 4)]);
        }

        $this->assertSame(
            [[1, 0, 2], [-1, 0, 4], [-1, 0, 3]],
            array_map(static fn (Neighbor $neighbor) => [$neighbor->offset->x, $neighbor->offset->y, $neighbor->sprite->id], $counts->result()->neighbors),
            'Less than 10% of the cells',
        );
    }

    public function testJson(): void
    {
        $neighbors = new OrientationNeighbors(new Orientation(2), 5, [new Neighbor(new CellOffset(3, -3), new OrientedSprite(3, new Orientation(1, true)), 4, 0.8)]);

        $this->assertSame(
            '{"rotation":2,"flip":false,"cells":5,"neighbors":[{"x":3,"y":-3,"id":3,"rotation":1,"flip":true,"count":4,"frequency":0.8}]}',
            json_encode($neighbors),
        );
        $this->assertEquals($neighbors, OrientationNeighbors::fromArray((array) json_decode((string) json_encode($neighbors), true)));
    }

    public function testKeys(): void
    {
        $this->assertSame('0', new Orientation()->key());
        $this->assertSame('3f', new Orientation(3, true)->key());
        $this->assertEquals(new Orientation(3, true), Orientation::fromKey('3f'));
        $this->assertEquals(new OrientedSprite(12, new Orientation(1, true)), OrientedSprite::fromKey('12:1f'));
        $this->assertSame('-1,2', new CellOffset(-1, 2)->key());
        $this->assertEquals(new CellOffset(-1, 2), CellOffset::fromKey('-1,2'));
    }
}
