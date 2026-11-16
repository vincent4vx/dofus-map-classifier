<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\MapSprites;
use MapClassifier\Sprite\Model\MapSpriteUsage;
use MapClassifier\Sprite\Model\SpriteSet;
use PHPUnit\Framework\TestCase;

use function array_map;
use function iterator_to_array;

final class MapSpritesTest extends TestCase
{
    public function testOneUsagePerSprite(): void
    {
        $sprites = new MapSprites();
        $sprites->addCell(Layer::Ground, 12, true);
        $sprites->addCell(Layer::Layer2, 12);
        $sprites->addCell(Layer::Layer1, 12);
        $sprites->addCell(Layer::Ground, 12);
        $sprites->addCell(Layer::Background, 12);
        $sprites->addCell(Layer::Layer2, 7);

        $this->assertCount(3, $sprites);
        $this->assertSame(['g12', 'o12', 'o7'], array_map(static fn (MapSpriteUsage $usage) => $usage->set->spriteName($usage->id), iterator_to_array($sprites, false)));

        $ground = $sprites->get(SpriteSet::Grounds, 12);
        $this->assertNotNull($ground);
        $this->assertSame(['ground' => 2, 'background' => 1], $ground->cells);
        $this->assertSame(1, $ground->slopedCells);

        $object = $sprites->get(SpriteSet::Objects, 12);
        $this->assertNotNull($object);
        $this->assertSame(['layer2' => 1, 'layer1' => 1], $object->cells, 'Same number, but different sets');
        $this->assertSame(0, $object->slopedCells);

        $this->assertNull($sprites->get(SpriteSet::Grounds, 7));
    }
}
