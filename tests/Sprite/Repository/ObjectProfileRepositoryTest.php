<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Repository;

use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\ObjectProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\ObjectProfileRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function json_decode;

final class ObjectProfileRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testSaveAndLoad(): void
    {
        $repository = new ObjectProfileRepository($this->tempDir() . '/objects.json');
        $this->assertFalse($repository->exists());

        $profiles = [
            'grounds' => [0 => new ObjectProfile(SpriteSet::Grounds, 0, Layer::Ground, 5, 2, 1, [['id' => 1, 'name' => 'Area', 'maps' => 2]], [], ['plain' => 1], ['outdoor' => 1], ['wild' => 1])],
            'objects' => [7511 => new ObjectProfile(SpriteSet::Objects, 7511, Layer::Layer2, 3, 1, 0, [], [], [], [], [], new InteractiveObject(38, 'Wheat', 1))],
        ];

        $repository->save($profiles, static fn (ObjectProfile $profile) => ['type' => $profile->id === 7511 ? 'wheat' : null]);

        $this->assertTrue($repository->exists());
        $this->assertEquals($profiles, $repository->all());

        $data = json_decode((string) file_get_contents($repository->file), true);
        $this->assertSame('wheat', $data['objects']['7511']['type']);
        $this->assertNull($data['grounds']['0']['type']);
        $this->assertStringContainsString('"grounds": {', (string) file_get_contents($repository->file), 'Sprites are indexed by id, even if sequential');
    }

    public function testMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('objects.json not found');

        new ObjectProfileRepository($this->tempDir() . '/objects.json')->all();
    }
}
