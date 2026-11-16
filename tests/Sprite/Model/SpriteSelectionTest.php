<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use InvalidArgumentException;
use MapClassifier\Sprite\Model\SpriteSelection;
use MapClassifier\Sprite\Model\SpriteSet;
use PHPUnit\Framework\TestCase;

final class SpriteSelectionTest extends TestCase
{
    public function testMatches(): void
    {
        $selection = SpriteSelection::parse(['o345', 'g10-20', '7']);

        $this->assertTrue($selection->matches(SpriteSet::Objects, 345));
        $this->assertFalse($selection->matches(SpriteSet::Grounds, 345));
        $this->assertTrue($selection->matches(SpriteSet::Grounds, 10));
        $this->assertTrue($selection->matches(SpriteSet::Grounds, 20));
        $this->assertFalse($selection->matches(SpriteSet::Grounds, 21));
        $this->assertFalse($selection->matches(SpriteSet::Objects, 15));
        $this->assertTrue($selection->matches(SpriteSet::Grounds, 7));
        $this->assertTrue($selection->matches(SpriteSet::Objects, 7));
    }

    public function testEmptySelectionMatchesAll(): void
    {
        $this->assertTrue(SpriteSelection::parse([])->matches(SpriteSet::Objects, 1));
    }

    public function testInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sprite id or range "x12"');

        SpriteSelection::parse(['x12']);
    }

    public function testSpriteName(): void
    {
        $this->assertSame('o345', SpriteSet::Objects->spriteName(345));
        $this->assertSame('g10', SpriteSet::Grounds->spriteName(10));
    }
}
