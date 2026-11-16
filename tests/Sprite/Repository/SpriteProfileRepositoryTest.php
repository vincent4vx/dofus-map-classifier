<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Repository;

use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\LocationUsage;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function json_decode;

final class SpriteProfileRepositoryTest extends TestCase
{
    use TempDirTrait;

    public function testSaveAndLoad(): void
    {
        $repository = new SpriteProfileRepository($this->tempDir() . '/sprite_profiles.json');
        $this->assertFalse($repository->exists());

        $profiles = SpriteProfiles::of([
            new SpriteProfile(SpriteSet::Objects, 7511, Layer::Layer2, 3, 1, 0, [], [], [], [], [], new InteractiveObject(38, 'Wheat', 1)),
            new SpriteProfile(SpriteSet::Grounds, 0, Layer::Ground, 5, 2, 1, [new LocationUsage(1, 'Area', 2)], [], ['plain' => 1], ['outdoor' => 1], ['wild' => 1]),
        ]);

        $repository->save($profiles);

        $this->assertTrue($repository->exists());
        $this->assertEquals($profiles, $repository->all());

        $data = json_decode((string) file_get_contents($repository->file), true);
        $this->assertSame(['id' => 38, 'name' => 'Wheat', 'type' => 'resource'], $data['objects']['7511']['interactive']);
        $this->assertArrayNotHasKey('type', $data['objects']['7511'], 'Types are only joined by the export');
        $this->assertStringContainsString('"grounds": {', (string) file_get_contents($repository->file), 'Sprites are indexed by id, even if sequential');
    }

    public function testMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sprite_profiles.json not found');

        new SpriteProfileRepository($this->tempDir() . '/sprite_profiles.json')->all();
    }
}
