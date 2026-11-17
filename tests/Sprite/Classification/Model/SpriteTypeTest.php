<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Model;

use InvalidArgumentException;
use MapClassifier\Sprite\Classification\Model\SpriteCategory;
use MapClassifier\Sprite\Classification\Model\SpriteOrientation;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteUsage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpriteTypeTest extends TestCase
{
    public function testFromArray(): void
    {
        $type = SpriteType::fromArray([
            'category' => 'tree',
            'name' => ' Pine Tree ',
            'keywords' => ['Tree', ' conifer ', 'tree', '', 'Snowy'],
            'description' => ' A snowy pine tree. ',
            'transition' => false,
            'usage' => 'big_object',
            'orientation' => 'bottom_left',
        ]);

        $this->assertSame(SpriteCategory::Tree, $type->category);
        $this->assertSame('pine tree', $type->name);
        $this->assertSame(['tree', 'conifer', 'snowy'], $type->keywords);
        $this->assertSame('A snowy pine tree.', $type->description);
        $this->assertSame([
            'category' => 'tree',
            'name' => 'pine tree',
            'keywords' => ['tree', 'conifer', 'snowy'],
            'description' => 'A snowy pine tree.',
            'transition' => false,
            'usage' => 'big_object',
            'orientation' => 'bottom_left',
        ], $type->jsonSerialize());
    }

    public function testTransition(): void
    {
        $base = ['category' => 'ground', 'name' => 'shore', 'keywords' => [], 'description' => ''];

        $this->assertTrue(SpriteType::fromArray($base + ['transition' => true])->transition);
        $this->assertTrue(SpriteType::fromArray($base + ['transition' => true])->withName('beach')->transition, 'Kept when renamed');
        $this->assertNull(SpriteType::fromArray($base)->transition, 'Stored before the field was asked to the model');
    }

    public function testUsage(): void
    {
        $base = ['category' => 'wall', 'name' => 'rampart', 'keywords' => [], 'description' => ''];

        $this->assertSame(SpriteUsage::Wall, SpriteType::fromArray($base + ['usage' => 'wall'])->usage);
        $this->assertSame(SpriteUsage::Wall, SpriteType::fromArray($base + ['usage' => 'wall'])->withName('wall')->usage, 'Kept when renamed');
        $this->assertNull(SpriteType::fromArray($base)->usage, 'Ground, or stored before the field was asked to the model');
    }

    public function testOrientation(): void
    {
        $base = ['category' => 'water', 'name' => 'shore', 'keywords' => [], 'description' => ''];

        $this->assertSame(SpriteOrientation::Bottom, SpriteType::fromArray($base + ['orientation' => 'bottom'])->orientation);
        $this->assertSame(SpriteOrientation::Bottom, SpriteType::fromArray($base + ['orientation' => 'bottom'])->withName('beach')->orientation, 'Kept when renamed');
        $this->assertNull(SpriteType::fromArray($base + ['orientation' => null])->orientation, 'No orientation');
    }

    public function testOrientationFlipped(): void
    {
        $this->assertSame(SpriteOrientation::TopLeft, SpriteOrientation::TopRight->flipped());
        $this->assertSame(SpriteOrientation::BottomRight, SpriteOrientation::BottomLeft->flipped());
        $this->assertSame(SpriteOrientation::Left, SpriteOrientation::Right->flipped());
        $this->assertSame(SpriteOrientation::Top, SpriteOrientation::Top->flipped());
        $this->assertSame(SpriteOrientation::Bottom, SpriteOrientation::Bottom->flipped());

        foreach (SpriteOrientation::cases() as $orientation) {
            $this->assertSame($orientation, $orientation->flipped()->flipped());
        }
    }

    public function testWithName(): void
    {
        $type = SpriteType::fromArray([
            'category' => 'crop',
            'name' => 'wheat clump',
            'keywords' => ['cereal', 'yellow', 'flax'],
            'description' => 'A clump.',
        ])->withName('Flax', 'resource');

        $this->assertSame(SpriteCategory::Crop, $type->category);
        $this->assertSame('flax', $type->name);
        $this->assertSame(['cereal', 'yellow', 'wheat clump', 'resource'], $type->keywords);
        $this->assertSame('A clump.', $type->description);
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('provideInvalid')]
    public function testInvalid(array $override, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        SpriteType::fromArray($override + ['category' => 'tree', 'name' => 'tree', 'keywords' => [], 'description' => '']);
    }

    /**
     * @return iterable<array{array<string, mixed>, string}>
     */
    public static function provideInvalid(): iterable
    {
        yield [['category' => 'spaceship'], 'Invalid category: spaceship'];
        yield [['category' => null], 'Invalid category'];
        yield [['name' => ' '], 'Invalid name'];
        yield [['keywords' => 'tree'], 'Invalid keywords'];
        yield [['keywords' => [1]], 'Invalid keywords'];
        yield [['description' => null], 'Invalid description'];
        yield [['transition' => 'yes'], 'Invalid transition'];
        yield [['usage' => 'spaceship'], 'Invalid usage'];
        yield [['usage' => 1], 'Invalid usage'];
        yield [['orientation' => 'north'], 'Invalid orientation'];
    }
}
