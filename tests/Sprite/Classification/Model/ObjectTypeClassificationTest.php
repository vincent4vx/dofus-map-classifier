<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Model;

use InvalidArgumentException;
use MapClassifier\Sprite\Classification\Model\ObjectCategory;
use MapClassifier\Sprite\Classification\Model\ObjectTypeClassification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ObjectTypeClassificationTest extends TestCase
{
    public function testFromArray(): void
    {
        $type = ObjectTypeClassification::fromArray([
            'category' => 'tree',
            'name' => ' Pine Tree ',
            'keywords' => ['Tree', ' conifer ', 'tree', '', 'Snowy'],
            'description' => ' A snowy pine tree. ',
        ]);

        $this->assertSame(ObjectCategory::Tree, $type->category);
        $this->assertSame('pine tree', $type->name);
        $this->assertSame(['tree', 'conifer', 'snowy'], $type->keywords);
        $this->assertSame('A snowy pine tree.', $type->description);
        $this->assertSame([
            'category' => 'tree',
            'name' => 'pine tree',
            'keywords' => ['tree', 'conifer', 'snowy'],
            'description' => 'A snowy pine tree.',
        ], $type->toArray());
    }

    public function testWithName(): void
    {
        $type = ObjectTypeClassification::fromArray([
            'category' => 'crop',
            'name' => 'wheat clump',
            'keywords' => ['cereal', 'yellow', 'flax'],
            'description' => 'A clump.',
        ])->withName('Flax', 'resource');

        $this->assertSame(ObjectCategory::Crop, $type->category);
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

        ObjectTypeClassification::fromArray($override + ['category' => 'tree', 'name' => 'tree', 'keywords' => [], 'description' => '']);
    }

    public static function provideInvalid(): iterable
    {
        yield [['category' => 'spaceship'], 'Invalid category: spaceship'];
        yield [['category' => null], 'Invalid category'];
        yield [['name' => ' '], 'Invalid name'];
        yield [['keywords' => 'tree'], 'Invalid keywords'];
        yield [['keywords' => [1]], 'Invalid keywords'];
        yield [['description' => null], 'Invalid description'];
    }
}
