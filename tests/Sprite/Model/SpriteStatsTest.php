<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\MapSpriteUsage;
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
