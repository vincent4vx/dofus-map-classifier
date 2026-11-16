<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Repository;

use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Repository\InteractiveObjectRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InteractiveObjectRepositoryTest extends TestCase
{
    use TempDirTrait;

    private const string FIXTURES = __DIR__ . '/../../Fixtures/lang';

    public function testByGfx(): void
    {
        $objects = (new InteractiveObjectRepository(self::FIXTURES))->byGfx();

        $this->assertEquals(new InteractiveObject(38, 'Wheat', 1), $objects[7511]);
        $this->assertSame(['id' => 38, 'name' => 'Wheat', 'type' => 'resource'], $objects[7511]->toArray());
        $this->assertSame('zaap', $objects[7000]->typeName());
        $this->assertSame('workshop', $objects[7003]->typeName());
        $this->assertSame($objects[7008], $objects[7009], 'Several sprites can draw the same interactive object');
        $this->assertArrayNotHasKey(1, $objects);
        $this->assertCount(194, $objects);
    }

    public function testFromArray(): void
    {
        $object = new InteractiveObject(38, 'Wheat', 1);

        $this->assertEquals($object, InteractiveObject::fromArray($object->toArray()));
        $this->assertSame(0, InteractiveObject::fromArray(['id' => 1, 'name' => 'Foo', 'type' => 'unknown'])->type);
        $this->assertSame('other', InteractiveObject::fromArray(['id' => 1, 'name' => 'Foo', 'type' => 'unknown'])->typeName());
    }

    public function testMissingFile(): void
    {
        $repository = new InteractiveObjectRepository($this->tempDir());
        $errors = [];

        $this->assertSame([], $repository->tryByGfx(static function (string $error) use (&$errors) { $errors[] = $error; }));
        $this->assertStringContainsString('No interactiveobjects_en_*.swf lang file found', $errors[0]);

        $this->expectException(RuntimeException::class);
        $repository->byGfx();
    }
}
